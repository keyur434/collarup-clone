<?php

/**
 * Routes outbound notification mail: live recipients, test inbox, or log-only (no SMTP).
 */
class MailDeliveryService
{
    private $forcedMode;

    public function __construct($forcedMode = null)
    {
        if ($forcedMode !== null) {
            $forcedMode = strtolower(trim((string) $forcedMode));
            if (in_array($forcedMode, ['live', 'test', 'log'], true)) {
                $this->forcedMode = $forcedMode;
            }
        }
    }

    public function mode()
    {
        if ($this->forcedMode !== null) {
            return $this->forcedMode;
        }
        $mode = strtolower(trim((string) env('NOTIFY_EMAIL_MODE', 'live')));
        if (!in_array($mode, ['live', 'test', 'log'], true)) {
            return 'live';
        }

        return $mode;
    }

    public function testInbox()
    {
        return trim((string) env('NOTIFY_EMAIL_TEST_TO', ''));
    }

    /**
     * @return array{to:string,subject:string,body:string,status_hint:string,meta:array}
     */
    public function prepare($intendedTo, $subject, $htmlBody, array $meta = [])
    {
        $intendedTo = trim((string) $intendedTo);
        $mode = $this->mode();
        $extraMeta = ['intended_recipient' => $intendedTo, 'delivery_mode' => $mode];

        if ($mode === 'log') {
            return [
                'to' => $intendedTo,
                'subject' => $subject,
                'body' => $htmlBody,
                'status_hint' => 'dry_run',
                'meta' => array_merge($meta, $extraMeta),
            ];
        }

        if ($mode === 'test') {
            $testTo = $this->testInbox();
            if ($testTo === '' || strpos($testTo, '@') === false) {
                return [
                    'to' => $intendedTo,
                    'subject' => $subject,
                    'body' => $htmlBody,
                    'status_hint' => 'skipped',
                    'meta' => array_merge($meta, $extraMeta, [
                        'error' => 'NOTIFY_EMAIL_MODE=test but NOTIFY_EMAIL_TEST_TO is not set',
                    ]),
                ];
            }
            $banner = '<p style="background:#fef3c7;border:1px solid #f59e0b;padding:10px 12px;border-radius:6px;font-size:13px">'
                . '<strong>TEST delivery</strong> — live mode would send this to <strong>' . e($intendedTo) . '</strong>.</p>';
            return [
                'to' => $testTo,
                'subject' => '[TEST] ' . $subject,
                'body' => $this->injectAfterBodyOpen($htmlBody, $banner),
                'status_hint' => 'sent_test',
                'meta' => array_merge($meta, $extraMeta),
            ];
        }

        return [
            'to' => $intendedTo,
            'subject' => $subject,
            'body' => $htmlBody,
            'status_hint' => 'sent',
            'meta' => array_merge($meta, $extraMeta),
        ];
    }

    /**
     * @param array<int,string> $to
     * @param array<int,string> $cc
     * @return array{to:array,to_display:string,cc:array,subject:string,body:string,status_hint:string,meta:array}
     */
    public function prepareRecipients(array $to, array $cc, $subject, $htmlBody, array $meta = [])
    {
        $to = $this->normalizeList($to);
        $cc = $this->normalizeList($cc);
        $mode = $this->mode();
        $extraMeta = [
            'delivery_mode' => $mode,
            'intended_to' => $to,
            'intended_cc' => $cc,
        ];

        if ($mode === 'log') {
            return [
                'to' => $to,
                'to_display' => $this->joinEmails($to, $cc),
                'cc' => $cc,
                'subject' => $subject,
                'body' => $htmlBody,
                'status_hint' => 'dry_run',
                'meta' => array_merge($meta, $extraMeta),
            ];
        }

        if ($mode === 'test') {
            $testTo = $this->testInbox();
            if ($testTo === '' || strpos($testTo, '@') === false) {
                return [
                    'to' => $to,
                    'to_display' => $this->joinEmails($to, $cc),
                    'cc' => [],
                    'subject' => $subject,
                    'body' => $htmlBody,
                    'status_hint' => 'skipped',
                    'meta' => array_merge($meta, $extraMeta, [
                        'error' => 'NOTIFY_EMAIL_MODE=test but NOTIFY_EMAIL_TEST_TO is not set',
                    ]),
                ];
            }
            $banner = '<div style="background:#fef3c7;border:1px solid #f59e0b;padding:12px 14px;border-radius:6px;font-size:13px;margin-bottom:16px">'
                . '<strong>TEST delivery</strong><br>TO: ' . e($this->joinEmails($to))
                . '<br>CC: ' . e($cc !== [] ? $this->joinEmails($cc) : '—') . '</div>';

            return [
                'to' => [$testTo],
                'to_display' => $testTo,
                'cc' => [],
                'subject' => '[TEST] ' . $subject,
                'body' => $this->injectAfterBodyOpen($htmlBody, $banner),
                'status_hint' => 'sent_test',
                'meta' => array_merge($meta, $extraMeta),
            ];
        }

        return [
            'to' => $to,
            'to_display' => $this->joinEmails($to, $cc),
            'cc' => $cc,
            'subject' => $subject,
            'body' => $htmlBody,
            'status_hint' => 'sent',
            'meta' => array_merge($meta, $extraMeta),
        ];
    }

    public function describeMode()
    {
        $mode = $this->mode();
        if ($mode === 'test') {
            $to = $this->testInbox();
            return 'test → ' . ($to !== '' ? $to : '(set NOTIFY_EMAIL_TEST_TO)');
        }
        if ($mode === 'log') {
            return 'log (no SMTP; notification_log only)';
        }

        return 'live (real recipients)';
    }

    private function injectAfterBodyOpen($html, $snippet)
    {
        if (stripos($html, '<body') !== false) {
            return preg_replace('/<body[^>]*>/i', '$0' . $snippet, $html, 1);
        }

        return $snippet . $html;
    }

    private function normalizeList(array $emails)
    {
        $out = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && strpos($email, '@') !== false) {
                $out[$email] = $email;
            }
        }

        return array_values($out);
    }

    private function joinEmails(array $to, array $cc = [])
    {
        return implode(', ', array_merge($to, $cc));
    }
}
