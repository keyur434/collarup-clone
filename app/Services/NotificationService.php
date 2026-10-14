<?php

class NotificationService
{
    private $mail;
    private $delivery;
    /** @var string|null live|test|log — one-shot override (CLI tests) */
    private $modeOverride;

    public function __construct()
    {
        $this->mail = new MailService();
        $this->delivery = new MailDeliveryService();
    }

    public function withDeliveryMode($mode)
    {
        $mode = strtolower(trim((string) $mode));
        if (in_array($mode, ['live', 'test', 'log'], true)) {
            $this->modeOverride = $mode;
        }

        return $this;
    }

    public function notifyReportReady($interviewId, $force = false)
    {
        if (!$force && !AppSettingsService::getBool('notify_report_ready', true)) {
            return;
        }

        $builder = new ReportReadyEmailService();
        $emailCtx = $builder->buildContext($interviewId);
        if (!$emailCtx) {
            return;
        }

        $html = $builder->renderHtml($emailCtx['template_data']);
        $this->sendWithRecipients('report_ready', $emailCtx['to'], $emailCtx['cc'], $emailCtx['subject'], $html, [
            'interview_id' => $interviewId,
            'report_id' => $emailCtx['report_id'],
        ]);
    }

    public function notifyInterviewScheduled($interviewId, $explicitSend = null)
    {
        if ($explicitSend === false) {
            return;
        }
        if ($explicitSend === null && !AppSettingsService::getBool('notify_interview_invite', true)) {
            return;
        }

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
        if (!$row || empty($row['email']) || empty($row['scheduled_date']) || strpos($row['email'], '@pending.local') !== false) {
            return;
        }

        $joinUrl = url('join/' . $interviewId);
        $when = format_date($row['scheduled_date'], 'd M Y');
        if ($row['scheduled_start']) {
            $when .= ' at ' . format_time($row['scheduled_start']);
        }

        $company = AppSettingsService::get('company_name', config('app')['name']);
        $template = db()->fetch(
            'SELECT body_html FROM job_email_templates WHERE job_id = ? AND template_type = ?',
            [$row['job_id'], 'invited']
        );
        $subject = 'Interview scheduled — ' . $row['job_title'];
        if ($template && trim($template['body_html']) !== '') {
            $plain = $this->replaceInvitePlaceholders($template['body_html'], $row, $when, $joinUrl, $company);
            $body = $this->wrapHtml(nl2br(e($plain)));
        } else {
            $body = $this->wrapHtml(
                '<p>Hi ' . e($row['first_name']) . ',</p>'
                . '<p>Your interview for <strong>' . e($row['job_title']) . '</strong> is scheduled on <strong>' . e($when) . '</strong>.</p>'
                . ($row['meeting_url'] ? '<p>Meeting link: <a href="' . e($row['meeting_url']) . '">' . e($row['meeting_url']) . '</a></p>' : '')
                . '<p>Interview session page: <a href="' . e($joinUrl) . '">' . e($joinUrl) . '</a></p>'
            );
        }
        $this->send('interview_scheduled', $row['email'], $subject, $body, ['interview_id' => $interviewId]);
    }

    private function replaceInvitePlaceholders($text, $row, $when, $joinUrl, $company)
    {
        $map = [
            '[Candidate Name]' => $row['first_name'] . ' ' . $row['last_name'],
            '[Job Title]' => $row['job_title'],
            '[Company Name]' => $company,
            '[Expiry Date]' => $when,
            '[Contact Email]' => config('app')['mail_from'] ?? '',
            '[Hiring Manager Name]' => '',
            '[Start Your Interview Now]' => $joinUrl,
        ];
        return str_replace(array_keys($map), array_values($map), $text);
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

    private function sendWithRecipients($type, array $to, array $cc, $subject, $htmlBody, $meta = [])
    {
        $delivery = $this->deliveryForSend();
        $prepared = $delivery->prepareRecipients($to, $cc, $subject, $htmlBody, $meta);
        $to = $prepared['to'];
        $cc = $prepared['cc'];
        $subject = $prepared['subject'];
        $htmlBody = $prepared['body'];
        $meta = $prepared['meta'];

        $status = 'skipped';
        $error = isset($prepared['meta']['error']) ? $prepared['meta']['error'] : null;

        if ($prepared['status_hint'] === 'dry_run') {
            $status = 'dry_run';
        } else {
            try {
                if ($this->mail->isConfigured()) {
                    $primaryTo = count($to) === 1 ? $to[0] : $to;
                    $this->mail->send($primaryTo, $subject, $htmlBody, '', $cc);
                    $status = $prepared['status_hint'] === 'sent_test' ? 'sent_test' : 'sent';
                } else {
                    $status = 'skipped';
                    $error = 'SMTP not configured';
                }
            } catch (Exception $e) {
                $status = 'failed';
                $error = $e->getMessage();
            }
        }

        try {
            db()->insert('notification_log', [
                'notification_type' => $type,
                'recipient' => $prepared['to_display'],
                'subject' => $subject,
                'status' => $status,
                'error_message' => $error,
                'meta_json' => json_encode($meta),
            ]);
        } catch (Exception $e) {
        }
    }

    private function send($type, $to, $subject, $htmlBody, $meta = [])
    {
        $delivery = $this->deliveryForSend();
        $prepared = $delivery->prepare($to, $subject, $htmlBody, $meta);
        $to = $prepared['to'];
        $subject = $prepared['subject'];
        $htmlBody = $prepared['body'];
        $meta = $prepared['meta'];

        $status = 'skipped';
        $error = isset($prepared['meta']['error']) ? $prepared['meta']['error'] : null;

        if ($prepared['status_hint'] === 'dry_run') {
            $status = 'dry_run';
        } else {
            try {
                if ($this->mail->isConfigured()) {
                    $this->mail->send($to, $subject, $htmlBody);
                    $status = $prepared['status_hint'] === 'sent_test' ? 'sent_test' : 'sent';
                } else {
                    $status = 'skipped';
                    $error = 'SMTP not configured';
                }
            } catch (Exception $e) {
                $status = 'failed';
                $error = $e->getMessage();
            }
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

    private function deliveryForSend()
    {
        if ($this->modeOverride === null) {
            return $this->delivery;
        }

        return new MailDeliveryService($this->modeOverride);
    }

    private function wrapHtml($inner)
    {
        return '<!DOCTYPE html><html><body style="font-family:Inter,Arial,sans-serif;color:#111">'
            . $inner
            . '<p style="color:#666;font-size:12px;margin-top:24px">' . e(config('app')['name']) . '</p>'
            . '</body></html>';
    }
}
