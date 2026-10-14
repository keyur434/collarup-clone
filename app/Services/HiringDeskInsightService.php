<?php

/**
 * On-demand AI comparative brief for a job applicant pool (hiring desk preview only).
 */
class HiringDeskInsightService
{
    const PROMPT_VERSION = 'pool-brief-v1';
    const MAX_CANDIDATES_IN_PROMPT = 20;

    /**
     * @return array|null DB row
     */
    public static function getStored($jobId)
    {
        try {
            return db()->fetch('SELECT * FROM job_hiring_insights WHERE job_id = ?', [$jobId]);
        } catch (Exception $e) {
            return null;
        }
    }

    public static function isStale($jobId, $storedRow)
    {
        if (!$storedRow || empty($storedRow['input_hash'])) {
            return true;
        }
        return $storedRow['input_hash'] !== self::computeInputHash($jobId);
    }

    /**
     * @return array{row:array|null,data:array|null,stale:bool}
     */
    public static function getForDisplay($jobId)
    {
        $row = self::getStored($jobId);
        if (!$row || ($row['status'] ?? '') !== 'completed' || empty($row['insights_json'])) {
            return ['row' => $row, 'data' => null, 'stale' => self::isStale($jobId, $row)];
        }
        $data = json_decode($row['insights_json'], true);
        if (!is_array($data)) {
            $data = null;
        }
        return [
            'row' => $row,
            'data' => $data,
            'stale' => self::isStale($jobId, $row),
        ];
    }

    public static function generate($jobId, $userId = null)
    {
        PermissionService::requireJobAccess($jobId);

        $openai = new AzureOpenAIService();
        if (!$openai->isConfigured()) {
            throw new RuntimeException('Azure OpenAI is not configured. Set endpoint and API key in .env.');
        }

        $pool = self::loadPoolForInsight($jobId);
        if (count($pool['candidates']) < 2) {
            throw new RuntimeException('At least 2 candidates with AI interview reports are required.');
        }

        $context = self::buildAiContext($jobId, $pool);
        $inputHash = self::computeInputHash($jobId);

        self::upsertPending($jobId, $inputHash, $userId);

        try {
            $data = $openai->generateJobPoolBrief($context);
            self::upsertCompleted($jobId, $inputHash, $userId, $openai->getAnalysisDeployment(), $data);
            return $data;
        } catch (Exception $e) {
            self::upsertFailed($jobId, $inputHash, $userId, $e->getMessage());
            throw $e;
        }
    }

    public static function computeInputHash($jobId)
    {
        $job = db()->fetch(
            'SELECT id, updated_at, title, key_skills, qualifications, must_have_criteria, key_responsibility FROM jobs WHERE id = ?',
            [$jobId]
        );
        if (!$job) {
            return hash('sha256', 'missing-job');
        }

        $reports = db()->fetchAll(
            "SELECT a.id AS application_id, ir.id AS report_id, ir.created_at AS report_updated
             FROM applications a
             OUTER APPLY (
                 SELECT TOP 1 i2.id
                 FROM interviews i2
                 WHERE i2.application_id = a.id
                 ORDER BY COALESCE(i2.completed_at, i2.scheduled_date, i2.created_at) DESC, i2.created_at DESC
             ) i
             OUTER APPLY (
                 SELECT TOP 1 ir2.id, ir2.created_at
                 FROM interview_reports ir2
                 WHERE ir2.interview_id = i.id
                 ORDER BY ir2.created_at DESC
             ) ir
             WHERE a.job_id = ? AND (a.is_archived = 0 OR a.is_archived IS NULL) AND ir.id IS NOT NULL
             ORDER BY a.id",
            [$jobId]
        );

        $criteria = db()->fetchAll(
            'SELECT id, name, sort_order, is_active FROM job_rubric_criteria WHERE job_id = ? AND is_active = 1 ORDER BY sort_order',
            [$jobId]
        );

        $payload = [
            'job_updated' => $job['updated_at'],
            'job_jd' => HiringDeskResumeMatchService::jobJdHash($job),
            'reports' => $reports,
            'criteria' => $criteria,
            'prompt' => self::PROMPT_VERSION,
        ];

        return hash('sha256', json_encode($payload));
    }

    /**
     * @return array{job:array,candidates:array,distribution:array,rubric:array}
     */
    public static function loadPoolForInsight($jobId)
    {
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        $candidates = db()->fetchAll(
            "SELECT a.id AS application_id, a.decision,
                    c.first_name, c.last_name,
                    ir.id AS report_id, ir.overall_score, ir.experience_score, ir.culture_score,
                    ir.soft_skills_score, ir.score_label, ir.raw_analysis_json,
                    ir.overview_strengths, ir.overview_weaknesses, ir.overview_red_flags,
                    ir.overview_recommendation, ir.overview_qualification_match
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
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
             WHERE a.job_id = ? AND (a.is_archived = 0 OR a.is_archived IS NULL) AND ir.id IS NOT NULL
             ORDER BY ir.overall_score DESC, c.last_name ASC",
            [$jobId]
        );

        foreach ($candidates as &$row) {
            $row['criterion_scores'] = db()->fetchAll(
                'SELECT criterion_name, score FROM interview_criterion_scores WHERE report_id = ?',
                [$row['report_id']]
            );
            $row['compact'] = self::compactCandidateForPrompt($row);
        }
        unset($row);

        $distribution = HiringDeskService::scoreDistribution($candidates);
        $rubric = db()->fetchAll(
            'SELECT name, description FROM job_rubric_criteria WHERE job_id = ? AND is_active = 1 ORDER BY sort_order',
            [$jobId]
        );

        return [
            'job' => $job,
            'candidates' => $candidates,
            'distribution' => $distribution,
            'rubric' => $rubric,
        ];
    }

    private static function compactCandidateForPrompt(array $row)
    {
        $enhanced = [];
        if (!empty($row['raw_analysis_json'])) {
            $enhanced = json_decode($row['raw_analysis_json'], true);
            if (!is_array($enhanced)) {
                $enhanced = [];
            }
        }

        $qa = [];
        if (!empty($enhanced['qa_pairs']) && is_array($enhanced['qa_pairs'])) {
            foreach (array_slice($enhanced['qa_pairs'], 0, 6) as $pair) {
                $qa[] = [
                    'q' => mb_substr((string) ($pair['question'] ?? ''), 0, 200),
                    'a' => mb_substr((string) ($pair['answer_text'] ?? ''), 0, 350),
                ];
            }
        }

        $evidence = [];
        if (!empty($enhanced['criterion_scores']) && is_array($enhanced['criterion_scores'])) {
            foreach ($enhanced['criterion_scores'] as $cs) {
                $quotes = isset($cs['evidence_quotes']) && is_array($cs['evidence_quotes'])
                    ? array_slice($cs['evidence_quotes'], 0, 2) : [];
                $evidence[] = [
                    'criterion' => $cs['name'] ?? '',
                    'score' => $cs['score'] ?? null,
                    'quotes' => $quotes,
                ];
            }
        }

        return [
            'application_id' => $row['application_id'],
            'report_id' => $row['report_id'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'decision' => $row['decision'] ?? 'none',
            'scores' => [
                'overall' => $row['overall_score'],
                'experience' => $row['experience_score'],
                'culture' => $row['culture_score'],
                'soft_skills' => $row['soft_skills_score'],
            ],
            'criterion_scores' => $row['criterion_scores'],
            'must_have_check' => isset($enhanced['must_have_check']) ? $enhanced['must_have_check'] : [],
            'qa_sample' => $qa,
            'evidence_by_criterion' => $evidence,
            'overview_snippets' => [
                'strengths' => mb_substr(strip_tags((string) ($row['overview_strengths'] ?? '')), 0, 500),
                'weaknesses' => mb_substr(strip_tags((string) ($row['overview_weaknesses'] ?? '')), 0, 500),
                'red_flags' => mb_substr(strip_tags((string) ($row['overview_red_flags'] ?? '')), 0, 300),
                'recommendation' => mb_substr(strip_tags((string) ($row['overview_recommendation'] ?? '')), 0, 300),
            ],
        ];
    }

    private static function buildAiContext($jobId, array $pool)
    {
        $job = $pool['job'];
        $candidates = $pool['candidates'];
        $truncated = 0;
        if (count($candidates) > self::MAX_CANDIDATES_IN_PROMPT) {
            $truncated = count($candidates) - self::MAX_CANDIDATES_IN_PROMPT;
            $candidates = array_slice($candidates, 0, self::MAX_CANDIDATES_IN_PROMPT);
        }

        $compactList = array_map(function ($c) {
            return $c['compact'];
        }, $candidates);

        return [
            'job_id' => $jobId,
            'job_title' => $job['title'],
            'key_skills' => (string) ($job['key_skills'] ?? ''),
            'qualifications' => (string) ($job['qualifications'] ?? ''),
            'must_have' => (string) ($job['must_have_criteria'] ?? ''),
            'key_responsibility' => (string) ($job['key_responsibility'] ?? ''),
            'rubric' => $pool['rubric'],
            'pool_size_with_reports' => count($pool['candidates']),
            'pool_truncated_count' => $truncated,
            'score_distribution' => $pool['distribution'],
            'candidates' => $compactList,
        ];
    }

    private static function upsertPending($jobId, $hash, $userId)
    {
        $existing = self::getStored($jobId);
        $row = [
            'input_hash' => $hash,
            'prompt_version' => self::PROMPT_VERSION,
            'status' => 'pending',
            'error_message' => null,
            'generated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            db()->update('job_hiring_insights', $row, 'job_id = :jid', ['jid' => $jobId]);
        } else {
            db()->insert('job_hiring_insights', array_merge($row, [
                'job_id' => $jobId,
                'insights_json' => null,
                'model_name' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    private static function upsertCompleted($jobId, $hash, $userId, $model, array $data)
    {
        $row = [
            'input_hash' => $hash,
            'prompt_version' => self::PROMPT_VERSION,
            'model_name' => $model,
            'insights_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'status' => 'completed',
            'error_message' => null,
            'generated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $existing = self::getStored($jobId);
        if ($existing) {
            db()->update('job_hiring_insights', $row, 'job_id = :jid', ['jid' => $jobId]);
        } else {
            db()->insert('job_hiring_insights', array_merge($row, [
                'job_id' => $jobId,
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    private static function upsertFailed($jobId, $hash, $userId, $message)
    {
        $row = [
            'input_hash' => $hash,
            'prompt_version' => self::PROMPT_VERSION,
            'status' => 'failed',
            'error_message' => mb_substr($message, 0, 2000),
            'generated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $existing = self::getStored($jobId);
        if ($existing) {
            db()->update('job_hiring_insights', $row, 'job_id = :jid', ['jid' => $jobId]);
        } else {
            db()->insert('job_hiring_insights', array_merge($row, [
                'job_id' => $jobId,
                'insights_json' => null,
                'model_name' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }
}
