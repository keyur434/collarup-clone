<?php

class HiringDeskDecisionService
{
    public static function record($jobId, $applicationId, $noteType, $reason, array $extra = [])
    {
        PermissionService::requireJobAccess($jobId);
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Please enter a reason.');
        }
        $allowed = ['override_shortlist', 'agree_shortlist', 'general'];
        if (!in_array($noteType, $allowed, true)) {
            $noteType = 'general';
        }
        $app = db()->fetch('SELECT id FROM applications WHERE id = ? AND job_id = ?', [$applicationId, $jobId]);
        if (!$app) {
            throw new RuntimeException('Invalid application.');
        }

        db()->insert('hiring_desk_decision_notes', [
            'job_id' => $jobId,
            'application_id' => $applicationId,
            'user_id' => auth_id(),
            'note_type' => $noteType,
            'ai_suggested_rank' => isset($extra['ai_suggested_rank']) ? (int) $extra['ai_suggested_rank'] : null,
            'hr_rank' => isset($extra['hr_rank']) ? (int) $extra['hr_rank'] : null,
            'reason' => $reason,
        ]);
    }

    /**
     * @return array<int, array>
     */
    public static function listForJob($jobId, $limit = 50)
    {
        try {
            return db()->fetchAll(
                'SELECT n.*, u.first_name, u.last_name, c.first_name AS cand_first, c.last_name AS cand_last
                 FROM hiring_desk_decision_notes n
                 JOIN users u ON u.id = n.user_id
                 JOIN applications a ON a.id = n.application_id
                 JOIN candidates c ON c.id = a.candidate_id
                 WHERE n.job_id = ?
                 ORDER BY n.created_at DESC
                 OFFSET 0 ROWS FETCH NEXT ' . (int) $limit . ' ROWS ONLY',
                [$jobId]
            );
        } catch (Exception $e) {
            return [];
        }
    }
}
