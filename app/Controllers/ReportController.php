<?php

class ReportController
{
    public function show($jobId, $reportId)
    {
        $data = $this->buildReportViewData($jobId, $reportId);
        if (!$data) {
            http_response_code(404);
            die('Report not found');
        }
        PermissionService::requireReportAccess($jobId, $reportId);
        render('reports.show', $data);
    }

    public function sharedShow($token)
    {
        $row = $this->resolveSharedReportRow($token);
        $share = new ReportShareService();
        $share->recordView((int) $row['id']);

        $data = $this->buildReportViewData($row['job_id'], $row['report_id'], [
            'sharedReportView' => true,
            'sharedExportUrl' => url('reports/shared/' . $token . '/export'),
            'sharedReportUrl' => url('reports/shared/' . $token),
        ]);
        if (!$data) {
            http_response_code(404);
            die('Report not found');
        }
        render('reports.show', $data);
    }

    public function sharedExport($token)
    {
        $row = $this->resolveSharedReportRow($token);
        $this->exportReport($row['job_id'], $row['report_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSharedReportRow($token)
    {
        $share = new ReportShareService();
        $row = $share->findByToken($token);
        if (!$row) {
            http_response_code(404);
            die('This report link is invalid or has expired.');
        }

        $viewUrl = url('reports/shared/' . $token);
        if (report_share_require_login()) {
            if (!auth_check()) {
                auth_require_login_for_url($viewUrl);
            }
            AuthMiddleware::handle();
            if (!$share->loggedInUserCanAccessShare($row)) {
                http_response_code(403);
                die('Access denied. Sign in with your work account listed on this job (hiring manager, interviewer, or recruiter).');
            }
        }

        $share->grantSessionAccess((int) $row['report_id'], $row['access_token']);

        return $row;
    }

    public function updateStage($jobId, $reportId)
    {
        verify_csrf();
        PermissionService::requireEditReport($jobId);

        $stageId = isset($_POST['stage_id']) ? trim($_POST['stage_id']) : '';
        if ($stageId === '') {
            flash('error', 'Stage is required.');
            redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
        }

        $report = db()->fetch(
            'SELECT ir.id, i.id as interview_id, i.application_id
             FROM interview_reports ir
             JOIN interviews i ON i.id = ir.interview_id
             JOIN applications a ON a.id = i.application_id
             JOIN jobs j ON j.id = a.job_id
             WHERE ir.id = ? AND j.id = ?',
            [$reportId, $jobId]
        );
        if (!$report) {
            flash('error', 'Report not found.');
            redirect(url('jobs/' . $jobId . '/interviews'));
        }

        $stage = db()->fetch(
            'SELECT id FROM job_pipeline_stages WHERE id = ? AND job_id = ? AND is_active = 1',
            [$stageId, $jobId]
        );
        if (!$stage) {
            flash('error', 'Invalid stage for this job.');
            redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
        }

        $result = PipelineStageService::setApplicationStage($report['application_id'], $stageId);
        if (!$result) {
            flash('error', 'Could not move candidate to that stage.');
            redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
        }

        $msg = 'Moved to ' . $result['stage_name'] . '.';
        if (!empty($result['interview_id']) && $result['stage_type'] !== 'offer') {
            $msg .= ' Schedule the next round from Job interviews.';
            redirect(url('jobs/' . $jobId . '/interviews?focus=' . urlencode($result['interview_id'])));
        }

        flash('success', $msg);
        redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
    }

    public function decision($jobId, $reportId)
    {
        verify_csrf();
        PermissionService::requireEditReport($jobId);
        $decision = isset($_POST['decision']) ? trim($_POST['decision']) : '';
        $allowed = ['advance', 'offer', 'hold', 'reject', 'none'];
        if (!in_array($decision, $allowed, true)) {
            flash('error', 'Invalid decision.');
            redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
        }
        $report = db()->fetch(
            'SELECT ir.*, i.application_id, a.job_id
             FROM interview_reports ir
             JOIN interviews i ON i.id = ir.interview_id
             JOIN applications a ON a.id = i.application_id
             WHERE ir.id = ? AND a.job_id = ?',
            [$reportId, $jobId]
        );
        if ($report) {
            if ($decision === 'advance') {
                $result = PipelineStageService::advanceApplication($report['application_id']);
                if (!$result) {
                    flash('error', 'No further pipeline stage. Use Offer if this role has an offer stage, or update the job pipeline.');
                    redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
                }
                ActivityLogService::log('decision_advance', 'interview_report', $reportId);
                try {
                    $notify = new NotificationService();
                    $notify->notifyDecision($report['application_id'], 'advance');
                } catch (Exception $e) {
                }
                if ($result['stage_type'] === 'offer') {
                    flash('success', 'Candidate advanced to ' . $result['stage_name'] . ' (offer).');
                    redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
                }
                flash('success', 'Advanced to ' . $result['stage_name'] . '. Add the Teams link and schedule the next interview.');
                redirect(url('jobs/' . $jobId . '/interviews?focus=' . urlencode($result['interview_id'])));
            }

            if ($decision === 'offer') {
                $result = PipelineStageService::markOffer($report['application_id']);
                if (!$result) {
                    flash('error', 'This job has no Offer stage in the pipeline. Add one under job pipeline settings.');
                    redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
                }
                ActivityLogService::log('decision_offer', 'interview_report', $reportId);
                try {
                    $notify = new NotificationService();
                    $notify->notifyDecision($report['application_id'], 'offer');
                } catch (Exception $e) {
                }
                flash('success', 'Marked as offer — ' . $result['stage_name'] . '.');
                redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
            }

            db()->update('applications', [
                'decision' => $decision,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $report['application_id']]);
            ActivityLogService::log('decision_' . $decision, 'interview_report', $reportId);
            try {
                $notify = new NotificationService();
                $notify->notifyDecision($report['application_id'], $decision);
            } catch (Exception $e) {
            }
        }
        flash('success', 'Decision updated.');
        redirect(url('jobs/' . $jobId . '/reports/' . $reportId));
    }

    public function chat($jobId, $reportId)
    {
        verify_csrf();
        PermissionService::requireReportAccess($jobId, $reportId);
        $report = db()->fetch(
            'SELECT ir.interview_id FROM interview_reports ir
             JOIN interviews i ON i.id = ir.interview_id
             JOIN applications a ON a.id = i.application_id
             WHERE ir.id = ? AND a.job_id = ?',
            [$reportId, $jobId]
        );
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
        PermissionService::requireReportAccess($jobId, $reportId);
        $this->exportReport($jobId, $reportId);
    }

    private function exportReport($jobId, $reportId)
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

    private function buildReportViewData($jobId, $reportId, array $extra = [])
    {
        $report = db()->fetch(
            "SELECT ir.*, i.id as interview_id, i.application_id, i.stage_id as interview_stage_id,
                    i.scheduled_date, i.meeting_url,
                    a.current_stage_id, a.decision as application_decision,
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
            return null;
        }

        $shared = !empty($extra['sharedReportView']);
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
        $chatMessages = $shared ? [] : db()->fetchAll(
            'SELECT tcm.*, u.first_name, u.last_name, u.initials FROM team_chat_messages tcm JOIN users u ON u.id = tcm.user_id WHERE tcm.interview_id = ? ORDER BY tcm.created_at ASC',
            [$report['interview_id']]
        );
        $enhanced = [];
        if (!empty($report['raw_analysis_json'])) {
            $decoded = json_decode($report['raw_analysis_json'], true);
            if (is_array($decoded)) {
                $enhanced = $decoded;
            }
        }

        $stages = db()->fetchAll('SELECT id, name, stage_type FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$jobId]);
        $interviewRounds = $shared ? [] : ApplicationInterviewService::roundsForApplication($report['application_id']);
        $currentStageInterview = $shared ? null : ApplicationInterviewService::interviewForCurrentStage($report['application_id']);
        $hasOfferStage = $shared ? false : PipelineStageService::offerStageForJob($jobId) !== null;
        $managers = db()->fetchAll(
            'SELECT TOP 1 u.first_name, u.last_name FROM job_hiring_managers jhm JOIN users u ON u.id = jhm.user_id WHERE jhm.job_id = ?',
            [$jobId]
        );

        return array_merge([
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
            'canEditReport' => $shared ? false : PermissionService::canEditReport($jobId),
            'enhanced' => $enhanced,
            'interviewRounds' => $interviewRounds,
            'currentStageInterview' => $currentStageInterview,
            'hasOfferStage' => $hasOfferStage,
            'activeNav' => 'dashboard',
            'sharedReportView' => $shared,
        ], $extra);
    }
}
