<?php

class NotificationService
{
    private $mail;

    public function __construct()
    {
        $this->mail = new MailService();
    }

    public function notifyReportReady($interviewId)
    {
        if (!AppSettingsService::getBool('notify_report_ready', true)) {
            return;
        }

        $row = db()->fetch(
            'SELECT ir.id as report_id, ir.overall_score, ir.score_label,
                    c.first_name, c.last_name, c.email,
                    j.id as job_id, j.title as job_title,
                    u.email as manager_email, u.first_name as manager_first
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             JOIN interview_reports ir ON ir.interview_id = i.id
             LEFT JOIN job_hiring_managers jhm ON jhm.job_id = j.id
             LEFT JOIN users u ON u.id = jhm.user_id
             WHERE i.id = ?',
            [$interviewId]
        );
        if (!$row) {
            return;
        }

        $reportUrl = url('jobs/' . $row['job_id'] . '/reports/' . $row['report_id']);
        $subject = 'Interview report ready — ' . $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['job_title'] . ')';
        $body = $this->wrapHtml(
            '<p>AI analysis is complete for <strong>' . e($row['first_name'] . ' ' . $row['last_name']) . '</strong>.</p>'
            . '<p>Score: <strong>' . e($row['overall_score']) . '</strong> (' . e($row['score_label']) . ')</p>'
            . '<p><a href="' . e($reportUrl) . '">View report</a></p>'
        );

        $recipients = $this->jobNotificationRecipients($row['job_id'], $row['manager_email']);
        foreach ($recipients as $email) {
            $this->send('report_ready', $email, $subject, $body, ['interview_id' => $interviewId]);
        }
    }

    public function notifyInterviewScheduled($interviewId)
    {
        $row = db()->fetch(
            'SELECT i.scheduled_date, i.scheduled_start, i.scheduled_end, i.meeting_url,
                    c.first_name, c.last_name, c.email,
                    j.title as job_title, j.id as job_id
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             WHERE i.id = ?',
            [$interviewId]
        );
        if (!$row || empty($row['email']) || empty($row['scheduled_date'])) {
            return;
        }

        $joinUrl = url('join/' . $interviewId);
        $when = format_date($row['scheduled_date'], 'd M Y');
        if ($row['scheduled_start']) {
            $when .= ' at ' . format_time($row['scheduled_start']);
        }

        $subject = 'Interview scheduled — ' . $row['job_title'];
        $body = $this->wrapHtml(
            '<p>Hi ' . e($row['first_name']) . ',</p>'
            . '<p>Your interview for <strong>' . e($row['job_title']) . '</strong> is scheduled on <strong>' . e($when) . '</strong>.</p>'
            . ($row['meeting_url'] ? '<p>Meeting link: <a href="' . e($row['meeting_url']) . '">' . e($row['meeting_url']) . '</a></p>' : '')
            . '<p>Interview session page: <a href="' . e($joinUrl) . '">' . e($joinUrl) . '</a></p>'
        );
        $this->send('interview_scheduled', $row['email'], $subject, $body, ['interview_id' => $interviewId]);
    }

    public function notifyDecision($applicationId, $decision)
    {
        if (!AppSettingsService::getBool('notify_decision_email', true)) {
            return;
        }

        $row = db()->fetch(
            'SELECT a.id, a.job_id, c.first_name, c.last_name, c.email, j.title as job_title
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             WHERE a.id = ?',
            [$applicationId]
        );
        if (!$row || empty($row['email'])) {
            return;
        }

        $templateType = ($decision === 'reject') ? 'rejected' : 'invited';
        $template = db()->fetch(
            'SELECT subject, body_html FROM job_email_templates WHERE job_id = ? AND template_type = ?',
            [$row['job_id'], $templateType]
        );

        if ($decision === 'reject' && $template) {
            $subject = $this->replacePlaceholders($template['subject'] ?: 'Update on your application', $row);
            $body = $this->replacePlaceholders($template['body_html'], $row);
            $this->send('decision_' . $decision, $row['email'], $subject, $this->wrapHtml($body), ['application_id' => $applicationId]);
            return;
        }

        if ($decision === 'advance' || $decision === 'offer') {
            $subject = 'Update on your application — ' . $row['job_title'];
            $body = $this->wrapHtml(
                '<p>Hi ' . e($row['first_name']) . ',</p>'
                . '<p>Thank you for interviewing for <strong>' . e($row['job_title']) . '</strong>. '
                . 'We would like to move forward with your application.</p>'
                . '<p>Our team will contact you with next steps.</p>'
            );
            $this->send('decision_' . $decision, $row['email'], $subject, $body, ['application_id' => $applicationId]);
        }
    }

    private function replacePlaceholders($text, $row)
    {
        $map = [
            '{{first_name}}' => $row['first_name'],
            '{{last_name}}' => $row['last_name'],
            '{{candidate_name}}' => $row['first_name'] . ' ' . $row['last_name'],
            '{{job_title}}' => $row['job_title'],
            '{{company}}' => config('app')['name'],
        ];
        return str_replace(array_keys($map), array_values($map), $text);
    }

    private function jobNotificationRecipients($jobId, $managerEmail)
    {
        $emails = [];
        if ($managerEmail) {
            $emails[] = $managerEmail;
        }
        $managers = db()->fetchAll(
            'SELECT u.email FROM job_hiring_managers jhm JOIN users u ON u.id = jhm.user_id WHERE jhm.job_id = ? AND u.is_active = 1',
            [$jobId]
        );
        foreach ($managers as $m) {
            if (!empty($m['email'])) {
                $emails[] = $m['email'];
            }
        }
        return array_values(array_unique($emails));
    }

    private function send($type, $to, $subject, $htmlBody, $meta = [])
    {
        $status = 'skipped';
        $error = null;
        try {
            if ($this->mail->isConfigured()) {
                $this->mail->send($to, $subject, $htmlBody);
                $status = 'sent';
            } else {
                $status = 'skipped';
                $error = 'SMTP not configured';
            }
        } catch (Exception $e) {
            $status = 'failed';
            $error = $e->getMessage();
        }

        try {
            db()->insert('notification_log', [
                'notification_type' => $type,
                'recipient' => $to,
                'subject' => $subject,
                'status' => $status,
                'error_message' => $error,
                'meta_json' => json_encode($meta),
            ]);
        } catch (Exception $e) {
            // logging must not break pipeline
        }
    }

    private function wrapHtml($inner)
    {
        return '<!DOCTYPE html><html><body style="font-family:Inter,Arial,sans-serif;color:#111">'
            . $inner
            . '<p style="color:#666;font-size:12px;margin-top:24px">' . e(config('app')['name']) . '</p>'
            . '</body></html>';
    }
}
