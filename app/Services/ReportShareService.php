<?php

class ReportShareService
{
    /** Single magic link row for consolidated report-ready email (not a real mailbox). */
    const EMAIL_BUNDLE_SHARE_ADDRESS = 'report-bundle@share.talentpulse';

    /**
     * @return array<int, array{email:string, role:string, name:string}>
     */
    public function recipientsForJob($jobId)
    {
        $byEmail = [];

        $hms = db()->fetchAll(
            'SELECT u.email, u.first_name, u.last_name
             FROM job_hiring_managers jhm
             JOIN users u ON u.id = jhm.user_id
             WHERE jhm.job_id = ? AND u.is_active = 1 AND u.email IS NOT NULL AND LTRIM(RTRIM(u.email)) <> \'\'',
            [$jobId]
        );
        foreach ($hms as $row) {
            $this->addRecipient($byEmail, $row['email'], 'hiring_manager', trim($row['first_name'] . ' ' . ($row['last_name'] ?? '')));
        }

        $interviewers = db()->fetchAll(
            'SELECT u.email, u.first_name, u.last_name
             FROM job_interviewers ji
             JOIN users u ON u.id = ji.user_id
             WHERE ji.job_id = ? AND u.is_active = 1 AND u.email IS NOT NULL AND LTRIM(RTRIM(u.email)) <> \'\'',
            [$jobId]
        );
        foreach ($interviewers as $row) {
            $this->addRecipient($byEmail, $row['email'], 'interviewer', trim($row['first_name'] . ' ' . ($row['last_name'] ?? '')));
        }

        $externals = db()->fetchAll(
            'SELECT email, name, contact_role
             FROM job_external_contacts
             WHERE job_id = ? AND email IS NOT NULL AND LTRIM(RTRIM(email)) <> \'\'',
            [$jobId]
        );
        foreach ($externals as $row) {
            $role = $row['contact_role'] === 'interviewer' ? 'interviewer' : 'hiring_manager';
            $this->addRecipient($byEmail, $row['email'], $role, (string) $row['name']);
        }

        return array_values($byEmail);
    }

    /**
     * @return array{to:array<int,string>,cc:array<int,string>}
     */
    public function stakeholderMailRouting($jobId, $recruiterEmail = '')
    {
        $to = [];
        $cc = [];

        foreach ($this->interviewerRecipients($jobId) as $r) {
            $to[] = $r['email'];
        }
        foreach ($this->hiringManagerRecipients($jobId) as $r) {
            $cc[] = $r['email'];
        }

        $recruiterEmail = strtolower(trim((string) $recruiterEmail));
        if ($recruiterEmail !== '' && strpos($recruiterEmail, '@') !== false) {
            $cc[] = $recruiterEmail;
        }

        $to = $this->uniqueEmails($to);
        $cc = $this->uniqueEmails($cc);
        $cc = array_values(array_diff($cc, $to));

        return ['to' => $to, 'cc' => $cc];
    }

    public function sharedReportUrlForEmail($reportId)
    {
        $existing = db()->fetch(
            'SELECT access_token FROM interview_report_shares WHERE report_id = ? AND recipient_email = ?',
            [$reportId, self::EMAIL_BUNDLE_SHARE_ADDRESS]
        );
        if ($existing) {
            return url('reports/shared/' . $existing['access_token']);
        }

        $token = bin2hex(random_bytes(32));
        db()->insert('interview_report_shares', [
            'report_id' => $reportId,
            'recipient_email' => self::EMAIL_BUNDLE_SHARE_ADDRESS,
            'recipient_role' => 'interviewer',
            'access_token' => $token,
        ]);

        return url('reports/shared/' . $token);
    }

    /**
     * Create or reuse share rows; return map email => share URL.
     *
     * @return array<string, string>
     */
    public function shareUrlsForReport($reportId, $jobId)
    {
        $urls = [];
        foreach ($this->recipientsForJob($jobId) as $recipient) {
            $email = $recipient['email'];
            $existing = db()->fetch(
                'SELECT access_token FROM interview_report_shares WHERE report_id = ? AND LOWER(recipient_email) = LOWER(?)',
                [$reportId, $email]
            );
            if ($existing) {
                $token = $existing['access_token'];
            } else {
                $token = bin2hex(random_bytes(32));
                db()->insert('interview_report_shares', [
                    'report_id' => $reportId,
                    'recipient_email' => strtolower($email),
                    'recipient_role' => $recipient['role'],
                    'access_token' => $token,
                ]);
            }
            $urls[$email] = url('reports/shared/' . $token);
        }

        return $urls;
    }

    public function findByToken($token)
    {
        $token = trim((string) $token);
        if ($token === '' || strlen($token) !== 64) {
            return null;
        }

        return db()->fetch(
            'SELECT s.*, ir.interview_id, a.job_id
             FROM interview_report_shares s
             JOIN interview_reports ir ON ir.id = s.report_id
             JOIN interviews i ON i.id = ir.interview_id
             JOIN applications a ON a.id = i.application_id
             WHERE s.access_token = ?',
            [$token]
        );
    }

    public function recordView($shareId)
    {
        db()->query(
            'UPDATE interview_report_shares SET view_count = view_count + 1, last_viewed_at = SYSUTCDATETIME() WHERE id = ?',
            [$shareId]
        );
    }

    public function grantSessionAccess($reportId, $token = null)
    {
        if (!isset($_SESSION['report_share_grants']) || !is_array($_SESSION['report_share_grants'])) {
            $_SESSION['report_share_grants'] = [];
        }
        $_SESSION['report_share_grants'][$reportId] = [
            'token' => $token,
            'granted_at' => time(),
        ];
    }

    public static function sessionCanViewReport($reportId)
    {
        if (!isset($_SESSION['report_share_grants'][$reportId])) {
            return false;
        }
        $grant = $_SESSION['report_share_grants'][$reportId];
        if (empty($grant['token'])) {
            return false;
        }
        $share = (new self())->findByToken($grant['token']);
        if (!$share || (int) $share['report_id'] !== (int) $reportId) {
            return false;
        }
        if (report_share_require_login()) {
            if (!auth_check()) {
                return false;
            }

            return (new self())->loggedInUserCanAccessShare($share);
        }

        return true;
    }

    /**
     * After Microsoft / portal login: HM, interviewer, recruiter on application, or admin.
     *
     * @param array<string, mixed> $shareRow Row from findByToken()
     */
    public function loggedInUserCanAccessShare(array $shareRow)
    {
        $user = auth_user();
        if (!$user) {
            return false;
        }
        if (PermissionService::isAdminRole()) {
            return true;
        }
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email === '') {
            return false;
        }
        $jobId = (int) ($shareRow['job_id'] ?? 0);
        $reportId = (int) ($shareRow['report_id'] ?? 0);
        if ($jobId > 0 && $this->isStakeholderEmailOnJob($jobId, $email)) {
            return true;
        }
        if ($reportId > 0 && $this->isRecruiterEmailOnReport($reportId, $email)) {
            return true;
        }

        return false;
    }

    public function isRecruiterEmailOnReport($reportId, $email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return false;
        }
        $row = db()->fetch(
            'SELECT recruiter.email
             FROM interview_reports ir
             JOIN interviews i ON i.id = ir.interview_id
             JOIN applications a ON a.id = i.application_id
             LEFT JOIN users recruiter ON recruiter.id = a.recruiter_id AND recruiter.is_active = 1
             WHERE ir.id = ?',
            [$reportId]
        );
        if (!$row || empty($row['email'])) {
            return false;
        }

        return strtolower(trim((string) $row['email'])) === $email;
    }

    public function isStakeholderEmailOnJob($jobId, $email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return false;
        }
        foreach ($this->recipientsForJob($jobId) as $recipient) {
            if (strtolower($recipient['email']) === $email) {
                return true;
            }
        }
        return false;
    }

    private function interviewerRecipients($jobId)
    {
        $byEmail = [];
        $interviewers = db()->fetchAll(
            'SELECT u.email, u.first_name, u.last_name
             FROM job_interviewers ji
             JOIN users u ON u.id = ji.user_id
             WHERE ji.job_id = ? AND u.is_active = 1 AND u.email IS NOT NULL AND LTRIM(RTRIM(u.email)) <> \'\'',
            [$jobId]
        );
        foreach ($interviewers as $row) {
            $this->addRecipient($byEmail, $row['email'], 'interviewer', trim($row['first_name'] . ' ' . ($row['last_name'] ?? '')));
        }
        $externals = db()->fetchAll(
            'SELECT email, name FROM job_external_contacts
             WHERE job_id = ? AND contact_role = \'interviewer\' AND email IS NOT NULL AND LTRIM(RTRIM(email)) <> \'\'',
            [$jobId]
        );
        foreach ($externals as $row) {
            $this->addRecipient($byEmail, $row['email'], 'interviewer', (string) $row['name']);
        }

        return array_values($byEmail);
    }

    private function hiringManagerRecipients($jobId)
    {
        $byEmail = [];
        $hms = db()->fetchAll(
            'SELECT u.email, u.first_name, u.last_name
             FROM job_hiring_managers jhm
             JOIN users u ON u.id = jhm.user_id
             WHERE jhm.job_id = ? AND u.is_active = 1 AND u.email IS NOT NULL AND LTRIM(RTRIM(u.email)) <> \'\'',
            [$jobId]
        );
        foreach ($hms as $row) {
            $this->addRecipient($byEmail, $row['email'], 'hiring_manager', trim($row['first_name'] . ' ' . ($row['last_name'] ?? '')));
        }
        $externals = db()->fetchAll(
            'SELECT email, name FROM job_external_contacts
             WHERE job_id = ? AND contact_role = \'hiring_manager\' AND email IS NOT NULL AND LTRIM(RTRIM(email)) <> \'\'',
            [$jobId]
        );
        foreach ($externals as $row) {
            $this->addRecipient($byEmail, $row['email'], 'hiring_manager', (string) $row['name']);
        }

        return array_values($byEmail);
    }

    private function uniqueEmails(array $emails)
    {
        $out = [];
        foreach ($emails as $email) {
            $key = strtolower(trim((string) $email));
            if ($key === '' || strpos($key, '@') === false) {
                continue;
            }
            $out[$key] = $key;
        }

        return array_values($out);
    }

    private function addRecipient(array &$byEmail, $email, $role, $name)
    {
        $key = strtolower(trim((string) $email));
        if ($key === '' || strpos($key, '@') === false) {
            return;
        }
        if (!isset($byEmail[$key])) {
            $byEmail[$key] = [
                'email' => $key,
                'role' => $role,
                'name' => $name !== '' ? $name : $key,
            ];
            return;
        }
        if ($byEmail[$key]['role'] !== $role) {
            $byEmail[$key]['role'] = 'hiring_manager';
        }
    }
}
