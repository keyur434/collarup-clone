<?php

class ReportController
{
    public function show($jobId, $reportId)
    {
        $report = db()->fetch(
            "SELECT ir.*, i.id as interview_id, i.application_id, i.scheduled_date, i.meeting_url,
                    c.id as candidate_id, c.resume_path,
                    c.first_name, c.last_name, c.email, c.phone, c.phone_country_code,
                    j.title as job_title, j.id as job_id, d.name as department_name,
                    ia.cheating_likelihood, ia.external_display, ia.window_switching,
                    ia.content_copied, ia.ai_answer_patterns
             FROM interview_reports ir
             JOIN interviews i ON i.id = ir.interview_id
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN integrity_assessments ia ON ia.interview_id = i.id
             WHERE ir.id = ? AND j.id = ?",
            [$reportId, $jobId]
        );
        if (!$report) {
            http_response_code(404);
            die('Report not found');
        }

        PermissionService::requireJobAccess($jobId);

        $media = db()->fetch('SELECT TOP 1 * FROM interview_media WHERE interview_id = ? ORDER BY id DESC', [$report['interview_id']]);
        $videoUrl = '';
        $externalMeetingUrl = '';
        if ($media && $media['blob_name'] && $media['playback_mode'] === 'blob') {
            $videoUrl = url('api/video/' . $report['interview_id']);
        } elseif ($media && $media['external_url'] && $media['playback_mode'] === 'embed') {
            $externalMeetingUrl = $media['external_url'];
        } elseif (!empty($report['meeting_url'])) {
            $externalMeetingUrl = $report['meeting_url'];
        }

        $transcript = db()->fetch('SELECT TOP 1 * FROM transcripts WHERE interview_id = ? ORDER BY id DESC', [$report['interview_id']]);
        $qaNotes = db()->fetchAll('SELECT * FROM interview_qa_notes WHERE report_id = ? ORDER BY sort_order', [$reportId]);
        $criterionScores = db()->fetchAll('SELECT * FROM interview_criterion_scores WHERE report_id = ?', [$reportId]);
        $chatMessages = db()->fetchAll(
            'SELECT tcm.*, u.first_name, u.last_name, u.initials FROM team_chat_messages tcm JOIN users u ON u.id = tcm.user_id WHERE tcm.interview_id = ? ORDER BY tcm.created_at ASC',
            [$report['interview_id']]
        );
        $stages = db()->fetchAll('SELECT id, name FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$jobId]);
        $managers = db()->fetchAll(
            'SELECT TOP 1 u.first_name, u.last_name FROM job_hiring_managers jhm JOIN users u ON u.id = jhm.user_id WHERE jhm.job_id = ?',
            [$jobId]
        );

        render('reports.show', [
            'title' => 'Candidate Report',
            'report' => $report,
            'media' => $media,
            'videoUrl' => $videoUrl,
            'externalMeetingUrl' => $externalMeetingUrl,
            'transcript' => $transcript,
            'qaNotes' => $qaNotes,
            'criterionScores' => $criterionScores,
            'chatMessages' => $chatMessages,
            'stages' => $stages,
            'hiringManager' => !empty($managers) ? $managers[0]['first_name'] . ' ' . $managers[0]['last_name'] : '',
            'canEditReport' => PermissionService::canEditReport($jobId),
            'activeNav' => 'dashboard',
        ]);
    }

    public function decision($jobId, $reportId)
    {
        verify_csrf();
        PermissionService::requireEditReport($jobId);
        $decision = $_POST['decision'];
        $report = db()->fetch('SELECT ir.*, i.application_id FROM interview_reports ir JOIN interviews i ON i.id = ir.interview_id WHERE ir.id = ?', [$reportId]);
        if ($report) {
            db()->update('applications', ['decision' => $decision], 'id = :id', ['id' => $report['application_id']]);
            ActivityLogService::log('decision_' . $decision, 'interview_report', $reportId);
            try {
                $notify = new NotificationService();
                $notify->notifyDecision($report['application_id'], $decision);
            } catch (Exception $e) {
                // non-fatal
            }
        }
        flash('success', 'Decision updated.');
        redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
    }

    public function chat($jobId, $reportId)
    {
        verify_csrf();
        $report = db()->fetch('SELECT interview_id FROM interview_reports WHERE id = ?', [$reportId]);
        if ($report && !empty($_POST['message'])) {
            db()->insert('team_chat_messages', [
                'interview_id' => $report['interview_id'],
                'user_id' => auth_id(),
                'message_text' => trim($_POST['message']),
            ]);
        }
        redirect(url('jobs/' . $jobId . '/reports/' . $reportId . '?tab=chat'));
    }

    public function export($jobId, $reportId)
    {
        $report = db()->fetch(
            "SELECT ir.*, i.id as interview_id, c.first_name, c.last_name, j.title as job_title, d.name as department_name
             FROM interview_reports ir
             JOIN interviews i ON i.id = ir.interview_id
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE ir.id = ? AND j.id = ?",
            [$reportId, $jobId]
        );
        if (!$report) {
            http_response_code(404);
            die('Report not found');
        }
        PermissionService::requireJobAccess($jobId);

        $transcript = db()->fetch('SELECT TOP 1 * FROM transcripts WHERE interview_id = ? ORDER BY id DESC', [$report['interview_id']]);
        $qaNotes = db()->fetchAll('SELECT * FROM interview_qa_notes WHERE report_id = ? ORDER BY sort_order', [$reportId]);
        $criterionScores = db()->fetchAll('SELECT * FROM interview_criterion_scores WHERE report_id = ?', [$reportId]);

        echo view('reports.export', [
            'report' => $report,
            'transcript' => $transcript,
            'qaNotes' => $qaNotes,
            'criterionScores' => $criterionScores,
        ]);
    }
}
