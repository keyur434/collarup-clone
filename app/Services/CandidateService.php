<?php

class CandidateService
{
    public static function findByEmail($email)
    {
        return db()->fetch(
            'SELECT TOP 1 * FROM candidates WHERE LOWER(email) = LOWER(?)',
            [trim($email)]
        );
    }

    public static function applicationOnJob($candidateId, $jobId)
    {
        return db()->fetch(
            'SELECT * FROM applications WHERE candidate_id = ? AND job_id = ?',
            [$candidateId, $jobId]
        );
    }

    public static function otherApplications($candidateId, $excludeApplicationId = null)
    {
        $sql = 'SELECT a.id, a.job_id, a.status, a.is_archived, a.decision, j.title as job_title
                FROM applications a
                JOIN jobs j ON j.id = a.job_id
                WHERE a.candidate_id = ?';
        $params = [$candidateId];
        if ($excludeApplicationId) {
            $sql .= ' AND a.id <> ?';
            $params[] = $excludeApplicationId;
        }
        $sql .= ' ORDER BY a.created_at DESC';
        return db()->fetchAll($sql, $params);
    }

    public static function emailApplicationCount($email)
    {
        return (int) db()->fetch(
            'SELECT COUNT(*) as c FROM applications a JOIN candidates c ON c.id = a.candidate_id WHERE LOWER(c.email) = LOWER(?)',
            [trim($email)]
        )['c'];
    }

    public static function activityTimeline($candidateId, $applicationId)
    {
        $interviewIds = db()->fetchAll(
            'SELECT id FROM interviews WHERE application_id = ?',
            [$applicationId]
        );
        $ids = array_column($interviewIds, 'id');
        $entityIds = array_merge([$candidateId, $applicationId], $ids);

        if (empty($entityIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
        return db()->fetchAll(
            "SELECT al.*, u.first_name, u.last_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.entity_id IN ($placeholders)
             ORDER BY al.created_at DESC",
            $entityIds
        );
    }

    public static function moveToJob($applicationId, $newJobId, $stageId, $archiveCurrent = true)
    {
        $ctx = db()->fetch(
            'SELECT a.*, c.email FROM applications a JOIN candidates c ON c.id = a.candidate_id WHERE a.id = ?',
            [$applicationId]
        );
        if (!$ctx) {
            throw new RuntimeException('Application not found.');
        }

        $existing = self::applicationOnJob($ctx['candidate_id'], $newJobId);
        if ($existing) {
            throw new RuntimeException('Candidate already has an application on that job.');
        }

        $newAppId = uuid();
        db()->insert('applications', [
            'id' => $newAppId,
            'candidate_id' => $ctx['candidate_id'],
            'job_id' => $newJobId,
            'current_stage_id' => $stageId,
            'status' => 'pending',
            'decision' => 'none',
            'application_date' => date('Y-m-d'),
            'is_archived' => 0,
        ]);

        $interviewId = uuid();
        db()->insert('interviews', [
            'id' => $interviewId,
            'application_id' => $newAppId,
            'stage_id' => $stageId,
            'status' => 'pending',
        ]);

        if ($archiveCurrent) {
            db()->update('applications', [
                'is_archived' => 1,
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $applicationId]);
        }

        ActivityLogService::log('candidate_move_job', 'application', $newAppId, [
            'from_application_id' => $applicationId,
            'from_job_id' => $ctx['job_id'],
            'to_job_id' => $newJobId,
        ]);

        return $newAppId;
    }

    public static function reactivate($applicationId)
    {
        db()->update('applications', [
            'is_archived' => 0,
            'status' => 'pending',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $applicationId]);

        ActivityLogService::log('candidate_reactivate', 'application', $applicationId);
    }
}
