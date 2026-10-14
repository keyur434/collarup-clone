<?php

/**
 * Preview-only hiring decision support. Not used by legacy leaderboard, compare, or job/candidate UIs.
 */
class HiringDeskService
{
    public static function enabled()
    {
        if (AppSettingsService::getBool('hiring_desk_beta', false)) {
            return true;
        }
        return (bool) (config('app')['hiring_desk_beta'] ?? false);
    }

    public static function assertEnabled()
    {
        if (!self::enabled()) {
            http_response_code(404);
            die('Not found');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listJobs()
    {
        $where = "1=1";
        $params = [];
        list($accessSql, $accessParams) = PermissionService::jobListAccessClause('j');
        $where .= $accessSql;
        $params = array_merge($params, $accessParams);

        return db()->fetchAll(
            "SELECT j.id, j.title, j.status, d.name AS department_name,
                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND (a.is_archived = 0 OR a.is_archived IS NULL)) AS applicant_count,
                    (SELECT COUNT(DISTINCT ir.id)
                     FROM applications a2
                     JOIN interviews i2 ON i2.application_id = a2.id
                     JOIN interview_reports ir ON ir.interview_id = i2.id
                     WHERE a2.job_id = j.id AND (a2.is_archived = 0 OR a2.is_archived IS NULL)) AS report_count
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE {$where}
             ORDER BY j.title ASC",
            $params
        );
    }

    /**
     * Latest AI report per application on a job.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function applicantsForJob($jobId, array $filters = [])
    {
        PermissionService::requireJobAccess($jobId);

        $where = 'a.job_id = ? AND (a.is_archived = 0 OR a.is_archived IS NULL)';
        $params = [$jobId];

        if (!empty($filters['decision'])) {
            $where .= ' AND a.decision = ?';
            $params[] = $filters['decision'];
        }
        if (!empty($filters['has_report'])) {
            $where .= ' AND ir.id IS NOT NULL';
        }

        $jobRow = db()->fetch(
            'SELECT title, key_skills, qualifications, must_have_criteria, key_responsibility, updated_at FROM jobs WHERE id = ?',
            [$jobId]
        );
        $jobJdHash = $jobRow ? HiringDeskResumeMatchService::jobJdHash($jobRow) : null;

        $rows = db()->fetchAll(
            "SELECT a.id AS application_id, a.decision, a.status, a.application_date, a.match_score_grade,
                    c.id AS candidate_id, c.first_name, c.last_name, c.email,
                    ps.name AS stage_name,
                    i.id AS interview_id, i.completed_at,
                    ir.id AS report_id, ir.overall_score, ir.experience_score, ir.culture_score,
                    ir.soft_skills_score, ir.score_label,
                    ir.overview_strengths, ir.overview_weaknesses, ir.overview_red_flags,
                    ir.overview_recommendation
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             LEFT JOIN job_pipeline_stages ps ON ps.id = a.current_stage_id
             OUTER APPLY (
                 SELECT TOP 1 i2.id, i2.completed_at
                 FROM interviews i2
                 WHERE i2.application_id = a.id
                 ORDER BY COALESCE(i2.completed_at, i2.scheduled_date, i2.created_at) DESC, i2.created_at DESC
             ) i
             OUTER APPLY (
                 SELECT TOP 1 ir2.*
                 FROM interview_reports ir2
                 WHERE ir2.interview_id = i.id
                 ORDER BY ir2.created_at DESC
             ) ir
             WHERE {$where}
             ORDER BY ir.overall_score DESC, c.last_name ASC, c.first_name ASC",
            $params
        );

        foreach ($rows as &$row) {
            $row['criterion_scores'] = [];
            if (!empty($row['report_id'])) {
                $row['criterion_scores'] = db()->fetchAll(
                    'SELECT criterion_name, score FROM interview_criterion_scores WHERE report_id = ? ORDER BY criterion_name',
                    [$row['report_id']]
                );
            }
            $row['assist_rank'] = null;
            $match = HiringDeskResumeMatchService::latestMatchSummary($row['application_id'], $jobJdHash);
            $row['resume_match_score'] = null;
            $row['resume_match_summary'] = null;
            $row['resume_match_gaps'] = [];
            if ($match) {
                if (isset($match['match_score']) && $match['match_score'] !== '') {
                    $row['resume_match_score'] = $match['match_score'];
                }
                $row['resume_match_summary'] = trim((string) ($match['one_line_summary'] ?? ''));
                if (!empty($match['gaps_vs_jd']) && is_array($match['gaps_vs_jd'])) {
                    $row['resume_match_gaps'] = array_slice($match['gaps_vs_jd'], 0, 3);
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array{decision?:string,has_report?:bool} $filters
     */
    public static function filterApplicants(array $rows, array $filters)
    {
        $out = $rows;
        if (!empty($filters['decision'])) {
            $dec = $filters['decision'];
            $out = array_values(array_filter($out, function ($r) use ($dec) {
                return ($r['decision'] ?? 'none') === $dec;
            }));
        }
        if (!empty($filters['has_report'])) {
            $out = array_values(array_filter($out, function ($r) {
                return !empty($r['report_id']);
            }));
        }

        return $out;
    }

    /**
     * Rank pool rows for display (weighted Assist composite when set, else interview overall).
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public static function assignPoolRanks(array &$rows)
    {
        foreach ($rows as &$row) {
            $row['pool_rank'] = null;
        }
        unset($row);
        $scored = [];
        foreach ($rows as $idx => $row) {
            $val = null;
            if (isset($row['assist_composite']) && $row['assist_composite'] !== null && $row['assist_composite'] !== '') {
                $val = (float) $row['assist_composite'];
            } elseif ($row['overall_score'] !== null && $row['overall_score'] !== '') {
                $val = (float) $row['overall_score'];
            }
            if ($val !== null) {
                $scored[] = ['idx' => $idx, 'score' => $val];
            }
        }
        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $rank = 1;
        foreach ($scored as $item) {
            $rows[$item['idx']]['pool_rank'] = $rank++;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{count:int,min:?float,max:?float,avg:?float,q1:?float,q3:?float}
     */
    public static function scoreDistribution(array $rows)
    {
        $scores = [];
        foreach ($rows as $row) {
            if ($row['overall_score'] !== null && $row['overall_score'] !== '') {
                $scores[] = (float) $row['overall_score'];
            }
        }
        $n = count($scores);
        if ($n === 0) {
            return ['count' => 0, 'min' => null, 'max' => null, 'avg' => null, 'q1' => null, 'q3' => null];
        }
        sort($scores);
        $avg = array_sum($scores) / $n;
        $q1 = $scores[(int) floor(($n - 1) * 0.25)];
        $q3 = $scores[(int) floor(($n - 1) * 0.75)];

        return [
            'count' => $n,
            'min' => $scores[0],
            'max' => $scores[$n - 1],
            'avg' => round($avg, 1),
            'q1' => $q1,
            'q3' => $q3,
        ];
    }

    /**
     * @param string[] $applicationIds
     * @return array<int, array<string, mixed>>
     */
    public static function compareCandidates($jobId, array $applicationIds)
    {
        PermissionService::requireJobAccess($jobId);
        $applicationIds = array_values(array_unique(array_filter(array_map('trim', $applicationIds))));
        $applicationIds = array_slice($applicationIds, 0, 8);

        if (count($applicationIds) < 2) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
        $params = array_merge($applicationIds, [$jobId]);

        $candidates = db()->fetchAll(
            "SELECT a.id AS application_id, a.decision, a.status,
                    c.first_name, c.last_name, c.years_experience,
                    ps.name AS stage_name,
                    i.id AS interview_id,
                    ir.id AS report_id, ir.overall_score, ir.experience_score, ir.culture_score,
                    ir.soft_skills_score, ir.score_label, ir.overview_strengths, ir.overview_weaknesses,
                    ir.overview_recommendation
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             LEFT JOIN job_pipeline_stages ps ON ps.id = a.current_stage_id
             OUTER APPLY (
                 SELECT TOP 1 i2.id
                 FROM interviews i2
                 WHERE i2.application_id = a.id
                 ORDER BY COALESCE(i2.completed_at, i2.scheduled_date, i2.created_at) DESC, i2.created_at DESC
             ) i
             OUTER APPLY (
                 SELECT TOP 1 ir2.*
                 FROM interview_reports ir2
                 WHERE ir2.interview_id = i.id
                 ORDER BY ir2.created_at DESC
             ) ir
             WHERE a.id IN ($placeholders) AND a.job_id = ?
               AND (a.is_archived = 0 OR a.is_archived IS NULL)
             ORDER BY ir.overall_score DESC",
            $params
        );

        foreach ($candidates as &$c) {
            $c['criterion_scores'] = [];
            if (!empty($c['report_id'])) {
                $c['criterion_scores'] = db()->fetchAll(
                    'SELECT criterion_name, score FROM interview_criterion_scores WHERE report_id = ? ORDER BY criterion_name',
                    [$c['report_id']]
                );
            }
        }
        unset($c);

        return $candidates;
    }

    public static function heatClass($score)
    {
        if ($score === null || $score === '') {
            return 'hd-heat-empty';
        }
        $v = (float) $score;
        if ($v >= 8) {
            return 'hd-heat-good';
        }
        if ($v >= 6) {
            return 'hd-heat-mid';
        }
        return 'hd-heat-low';
    }
}
