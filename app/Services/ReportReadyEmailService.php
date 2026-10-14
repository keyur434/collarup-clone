<?php

class ReportReadyEmailService
{
    public function buildContext($interviewId)
    {
        $row = db()->fetch(
            'SELECT ir.id AS report_id, ir.overall_score, ir.score_label,
                    i.id AS interview_id, a.id AS application_id,
                    c.first_name, c.last_name,
                    a.current_ctc, a.expected_ctc, a.recruiter_id,
                    j.id AS job_id, j.title AS job_title,
                    recruiter.email AS recruiter_email
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             JOIN interview_reports ir ON ir.interview_id = i.id
             LEFT JOIN users recruiter ON recruiter.id = a.recruiter_id AND recruiter.is_active = 1
             WHERE i.id = ?',
            [$interviewId]
        );
        if (!$row) {
            return null;
        }

        $share = new ReportShareService();
        $routing = $share->stakeholderMailRouting($row['job_id'], $row['recruiter_email'] ?? '');
        if (empty($routing['to'])) {
            return null;
        }

        $sl = score_label($row['overall_score']);
        $scoreDisplay = '—';
        if ($row['overall_score'] !== null && $row['overall_score'] !== '') {
            $scoreDisplay = $row['overall_score'] . ' / 10';
            if (!empty($sl['label'])) {
                $scoreDisplay .= ' (' . $sl['label'] . ')';
            }
        }

        $candidateName = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
        $companyName = AppSettingsService::get('company_name', config('app')['name']);
        $reportUrl = $share->sharedReportUrlForEmail($row['report_id']);

        return [
            'interview_id' => $row['interview_id'],
            'report_id' => $row['report_id'],
            'job_id' => $row['job_id'],
            'to' => $routing['to'],
            'cc' => $routing['cc'],
            'subject' => 'Interview assessment — ' . $candidateName . ' | ' . $row['job_title'],
            'template_data' => [
                'company_name' => $companyName,
                'candidate_name' => $candidateName,
                'job_title' => $row['job_title'],
                'current_ctc' => $this->displayOrDash($row['current_ctc']),
                'expected_ctc' => $this->displayOrDash($row['expected_ctc']),
                'score_display' => $scoreDisplay,
                'report_url' => $reportUrl,
            ],
        ];
    }

    public function renderHtml(array $templateData)
    {
        return view('emails.report_ready', $templateData);
    }

    private function displayOrDash($value)
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : '—';
    }
}
