<?php

class PipelineStageService
{
    public static function stagesForJob($jobId, $activeOnly = true)
    {
        $sql = 'SELECT * FROM job_pipeline_stages WHERE job_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, created_at ASC';
        return db()->fetchAll($sql, [$jobId]);
    }

    public static function nextStageId($jobId, $currentStageId)
    {
        $stages = self::stagesForJob($jobId, true);
        $idx = null;
        foreach ($stages as $i => $s) {
            if ($s['id'] === $currentStageId) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return null;
        }
        for ($j = $idx + 1; $j < count($stages); $j++) {
            if (in_array($stages[$j]['stage_type'], ['round', 'ai_interview', 'offer'], true)) {
                return $stages[$j]['id'];
            }
        }
        return null;
    }

    public static function offerStageForJob($jobId)
    {
        return db()->fetch(
            "SELECT TOP 1 id, name, stage_type FROM job_pipeline_stages
             WHERE job_id = ? AND stage_type = 'offer' AND is_active = 1
             ORDER BY sort_order ASC",
            [$jobId]
        );
    }

    /**
     * Move application to a specific stage; creates a pending interview for that stage when needed.
     * Does not rewrite older interview rows' stage_id (each round keeps its own interview).
     *
     * @return array|null stage_id, stage_name, stage_type, interview_id
     */
    public static function setApplicationStage($applicationId, $stageId)
    {
        $app = db()->fetch(
            'SELECT id, job_id, current_stage_id, decision FROM applications WHERE id = ?',
            [$applicationId]
        );
        if (!$app) {
            return null;
        }

        $stage = db()->fetch(
            'SELECT id, name, stage_type FROM job_pipeline_stages WHERE id = ? AND job_id = ? AND is_active = 1',
            [$stageId, $app['job_id']]
        );
        if (!$stage) {
            return null;
        }

        $appUpdate = [
            'current_stage_id' => $stageId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($stage['stage_type'] === 'offer') {
            $appUpdate['decision'] = 'offer';
            $appUpdate['status'] = 'completed';
        }

        db()->update('applications', $appUpdate, 'id = :id', ['id' => $applicationId]);

        $interviewId = null;
        if (!in_array($stage['stage_type'], ['applicant_pool'], true)) {
            $interviewId = ApplicationInterviewService::ensureInterviewForStage($applicationId, $stageId);
        }

        ActivityLogService::log('pipeline_stage_set', 'application', $applicationId, [
            'from_stage' => $app['current_stage_id'],
            'to_stage' => $stageId,
        ]);

        return [
            'stage_id' => $stageId,
            'stage_name' => $stage['name'],
            'stage_type' => $stage['stage_type'],
            'interview_id' => $interviewId,
            'job_id' => $app['job_id'],
        ];
    }

    /**
     * Move application to next pipeline stage and create a pending interview for that round.
     *
     * @return array|null metadata including interview_id for scheduling UI
     */
    public static function advanceApplication($applicationId)
    {
        $app = db()->fetch(
            'SELECT id, job_id, current_stage_id, candidate_id FROM applications WHERE id = ?',
            [$applicationId]
        );
        if (!$app || empty($app['current_stage_id'])) {
            return null;
        }

        $nextId = self::nextStageId($app['job_id'], $app['current_stage_id']);
        if (!$nextId) {
            return null;
        }

        $nextStage = db()->fetch(
            'SELECT id, name, stage_type FROM job_pipeline_stages WHERE id = ?',
            [$nextId]
        );
        if (!$nextStage) {
            return null;
        }

        $decision = $nextStage['stage_type'] === 'offer' ? 'offer' : 'advance';
        $status = $nextStage['stage_type'] === 'offer' ? 'completed' : 'pending';

        db()->update('applications', [
            'current_stage_id' => $nextId,
            'decision' => $decision,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $applicationId]);

        $interviewId = ApplicationInterviewService::ensureInterviewForStage($applicationId, $nextId);

        ActivityLogService::log('pipeline_advance', 'application', $applicationId, [
            'from_stage' => $app['current_stage_id'],
            'to_stage' => $nextId,
        ]);

        return [
            'stage_id' => $nextId,
            'stage_name' => $nextStage['name'],
            'stage_type' => $nextStage['stage_type'],
            'interview_id' => $interviewId,
            'job_id' => $app['job_id'],
        ];
    }

    public static function markOffer($applicationId)
    {
        $app = db()->fetch('SELECT id, job_id, current_stage_id FROM applications WHERE id = ?', [$applicationId]);
        if (!$app) {
            return null;
        }
        $offer = self::offerStageForJob($app['job_id']);
        if (!$offer) {
            return null;
        }

        $result = self::setApplicationStage($applicationId, $offer['id']);
        if (!$result) {
            return null;
        }

        db()->update('applications', [
            'decision' => 'offer',
            'status' => 'completed',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $applicationId]);

        ActivityLogService::log('pipeline_offer', 'application', $applicationId, []);
        $result['decision'] = 'offer';

        return $result;
    }

    public static function reassignCandidatesOffStage($jobId, $stageId)
    {
        $stages = self::stagesForJob($jobId, true);
        $fallback = null;
        foreach ($stages as $s) {
            if ($s['id'] !== $stageId) {
                $fallback = $s['id'];
            }
            if ($s['id'] === $stageId) {
                break;
            }
        }
        if (!$fallback && !empty($stages)) {
            $fallback = $stages[0]['id'];
        }
        if ($fallback) {
            db()->update('applications', [
                'current_stage_id' => $fallback,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'current_stage_id = :sid AND job_id = :jid', ['sid' => $stageId, 'jid' => $jobId]);
        }
    }
}
