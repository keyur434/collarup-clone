<?php

class ApplicationInterviewService
{
    /**
     * All interview rounds for an application (one row per interviews.stage_id).
     */
    public static function roundsForApplication($applicationId)
    {
        return db()->fetchAll(
            "SELECT i.id AS interview_id, i.status, i.scheduled_date, i.scheduled_start, i.scheduled_end,
                    i.meeting_url, i.stage_id, ps.name AS stage_name, ps.sort_order, ps.stage_type,
                    ir.id AS report_id, ir.overall_score, ir.score_label
             FROM interviews i
             JOIN job_pipeline_stages ps ON ps.id = i.stage_id
             OUTER APPLY (
                SELECT TOP 1 ir2.id, ir2.overall_score, ir2.score_label
                FROM interview_reports ir2
                WHERE ir2.interview_id = i.id
                ORDER BY ir2.created_at DESC
             ) ir
             WHERE i.application_id = ?
             ORDER BY ps.sort_order ASC, i.created_at ASC",
            [$applicationId]
        );
    }

    /** Interview row for the application's current pipeline stage (may be pending). */
    public static function interviewForCurrentStage($applicationId)
    {
        return db()->fetch(
            "SELECT i.*
             FROM applications a
             JOIN interviews i ON i.application_id = a.id AND i.stage_id = a.current_stage_id
             WHERE a.id = ?",
            [$applicationId]
        );
    }

    public static function ensureInterviewForStage($applicationId, $stageId)
    {
        $existing = db()->fetch(
            'SELECT id FROM interviews WHERE application_id = ? AND stage_id = ?',
            [$applicationId, $stageId]
        );
        if ($existing) {
            return $existing['id'];
        }

        $id = uuid();
        db()->insert('interviews', [
            'id' => $id,
            'application_id' => $applicationId,
            'stage_id' => $stageId,
            'status' => 'pending',
        ]);

        return $id;
    }
}
