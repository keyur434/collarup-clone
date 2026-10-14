<?php

class HiringDeskResumeMatchService
{
    public static function jobJdHash(array $job)
    {
        $payload = [
            'title' => $job['title'] ?? '',
            'key_skills' => $job['key_skills'] ?? '',
            'qualifications' => $job['qualifications'] ?? '',
            'must_have' => $job['must_have_criteria'] ?? '',
            'key_responsibility' => $job['key_responsibility'] ?? '',
            'updated_at' => $job['updated_at'] ?? '',
        ];

        return hash('sha256', json_encode($payload));
    }

    /**
     * Score resumes vs job JD for all applicants on a job (preview desk only).
     *
     * @return array{scored:int,skipped:int}
     */
    public static function scoreJobPool($jobId)
    {
        PermissionService::requireJobAccess($jobId);
        $openai = new AzureOpenAIService();
        if (!$openai->isConfigured()) {
            throw new RuntimeException('Azure OpenAI is not configured.');
        }

        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        $rows = db()->fetchAll(
            'SELECT a.id AS application_id, a.candidate_id, c.resume_path
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             WHERE a.job_id = ? AND (a.is_archived = 0 OR a.is_archived IS NULL)',
            [$jobId]
        );

        $parser = new ResumeParserService();
        $scored = 0;
        $skipped = 0;
        $ungraded = 0;
        $jdHash = self::jobJdHash($job);

        foreach ($rows as $row) {
            if (empty($row['resume_path'])) {
                $skipped++;
                continue;
            }
            $path = BASE_PATH . '/storage/resumes/' . $row['resume_path'];
            if (!is_readable($path)) {
                $skipped++;
                continue;
            }
            $ext = pathinfo($row['resume_path'], PATHINFO_EXTENSION);
            $text = $parser->extractTextOnly($path, $ext);
            if (strlen(trim($text)) < 80) {
                $skipped++;
                continue;
            }

            $hash = hash('sha256', $text);
            $cached = db()->fetch(
                'SELECT TOP 1 * FROM cv_summaries WHERE application_id = ? AND source_file_hash = ? ORDER BY created_at DESC',
                [$row['application_id'], $hash]
            );

            $summary = null;
            if ($cached && !empty($cached['summary_json'])) {
                $summary = json_decode($cached['summary_json'], true);
                if (!is_array($summary) || ($summary['_jd_hash'] ?? '') !== $jdHash) {
                    $summary = null;
                }
            }
            if ($summary === null) {
                $summary = $openai->analyzeResumeJdMatch([
                    'job_title' => $job['title'],
                    'key_skills' => $job['key_skills'],
                    'qualifications' => $job['qualifications'],
                    'must_have' => $job['must_have_criteria'],
                    'key_responsibility' => $job['key_responsibility'],
                    'resume_text' => mb_substr($text, 0, 12000),
                ]);
                if (!is_array($summary)) {
                    $summary = [];
                }
                $summary['_jd_hash'] = $jdHash;
                db()->insert('cv_summaries', [
                    'id' => uuid(),
                    'candidate_id' => $row['candidate_id'],
                    'application_id' => $row['application_id'],
                    'source_file_hash' => $hash,
                    'extracted_text' => mb_substr($text, 0, 50000),
                    'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                    'model_name' => $openai->getAnalysisDeployment(),
                ]);
            }

            $grade = self::normalizeMatchGrade($summary);
            if ($grade !== null) {
                db()->update('applications', [
                    'match_score_grade' => $grade,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $row['application_id']]);
            } else {
                $ungraded++;
            }
            $scored++;
        }

        return [
            'scored' => $scored,
            'skipped' => $skipped,
            'ungraded' => $ungraded,
            'graded' => self::countGradedOnJob($jobId),
        ];
    }

    public static function normalizeMatchGrade(array $summary)
    {
        $g = strtoupper(trim((string) ($summary['match_grade'] ?? '')));
        if (in_array($g, ['A', 'B', 'C'], true)) {
            return $g;
        }
        if (isset($summary['match_score']) && $summary['match_score'] !== '') {
            $score = (float) $summary['match_score'];
            if ($score >= 8) {
                return 'A';
            }
            if ($score >= 6) {
                return 'B';
            }
            if ($score >= 0) {
                return 'C';
            }
        }

        return null;
    }

    public static function countGradedOnJob($jobId)
    {
        try {
            return (int) db()->fetch(
                'SELECT COUNT(*) AS c FROM applications WHERE job_id = ? AND match_score_grade IS NOT NULL AND (is_archived = 0 OR is_archived IS NULL)',
                [$jobId]
            )['c'];
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Latest cached AI resume-vs-JD payload for an application (from cv_summaries).
     */
    public static function latestMatchSummary($applicationId, $jobJdHash = null)
    {
        try {
            $row = db()->fetch(
                'SELECT TOP 1 summary_json FROM cv_summaries WHERE application_id = ? ORDER BY created_at DESC',
                [$applicationId]
            );
            if (!$row || empty($row['summary_json'])) {
                return null;
            }
            $data = json_decode($row['summary_json'], true);
            if (!is_array($data)) {
                return null;
            }
            if ($jobJdHash !== null && ($data['_jd_hash'] ?? '') !== $jobJdHash) {
                return null;
            }

            return $data;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function gradeForApplication($applicationId)
    {
        try {
            $row = db()->fetch('SELECT match_score_grade FROM applications WHERE id = ?', [$applicationId]);
            return $row ? ($row['match_score_grade'] ?? null) : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
