<?php
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
$brand = '#5b21b6';
$brandLight = '#ede9fe';
$company = isset($company_name) && $company_name !== '' ? $company_name : 'TalentPulse';
$candidate = isset($candidate_name) ? $candidate_name : '';
$jobTitle = isset($job_title) ? $job_title : '';
$reportUrl = isset($report_url) ? $report_url : '#';
$score = isset($score_display) ? $score_display : '—';
$rows = [
    'Candidate' => $candidate,
    'Role' => $jobTitle,
    'Current CTC' => isset($current_ctc) ? $current_ctc : '—',
    'Expected CTC' => isset($expected_ctc) ? $expected_ctc : '—',
    'Interview score' => $score,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview report</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:'Segoe UI',Inter,Arial,sans-serif;color:#18181b;line-height:1.5">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(24,24,27,0.08)">
                <tr>
                    <td style="background:<?= $brand ?>;padding:28px 32px">
                        <p style="margin:0 0 6px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:rgba(255,255,255,0.85)"><?= e($company) ?></p>
                        <h1 style="margin:0;font-size:22px;font-weight:600;color:#ffffff">Interview assessment ready</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 32px 8px">
                        <p style="margin:0 0 20px;font-size:15px;color:#3f3f46">
                            The AI-assisted interview report for <strong><?= e($candidate) ?></strong> is now available for your review.
                        </p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e4e7;border-radius:8px;overflow:hidden;font-size:14px">
                            <?php $i = 0; foreach ($rows as $label => $value): $i++; ?>
                            <tr style="background:<?= $i % 2 === 0 ? '#fafafa' : '#ffffff' ?>">
                                <td style="padding:12px 16px;width:38%;color:#71717a;font-weight:500;border-bottom:1px solid #f4f4f5"><?= e($label) ?></td>
                                <td style="padding:12px 16px;color:#18181b;border-bottom:1px solid #f4f4f5"><?= e($value) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 32px 32px;text-align:center">
                        <a href="<?= e($reportUrl) ?>" style="display:inline-block;background:<?= $brand ?>;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:14px 28px;border-radius:8px;margin-top:8px">View full interview report</a>
                        <p style="margin:20px 0 0;font-size:12px;color:#a1a1aa">Sign in with your work Microsoft account to open this report. The link is confidential—do not forward outside the hiring team.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;background:<?= $brandLight ?>;font-size:12px;color:#6b7280;border-top:1px solid #e9d5ff">
                        Sent via <?= e($company) ?> TalentPulse · Automated notification
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
