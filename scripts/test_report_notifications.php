<?php

/**
 * Preview or send report-ready emails (magic links) on the server.
 *
 *   php scripts/test_report_notifications.php status
 *   php scripts/test_report_notifications.php list
 *   php scripts/test_report_notifications.php preview --interview=<uuid>
 *   php scripts/test_report_notifications.php send --interview=<uuid>
 *   php scripts/test_report_notifications.php send --interview=<uuid> --mode=test
 *   php scripts/test_report_notifications.php send --interview=<uuid> --mode=live
 *
 * Default send uses NOTIFY_EMAIL_MODE from .env (use test while validating).
 */
require __DIR__ . '/../app/bootstrap.php';

$cmd = isset($argv[1]) ? strtolower(trim($argv[1])) : '';
$opts = parseCliOpts($argv);

if ($cmd === '' || $cmd === 'help' || in_array('--help', $argv, true)) {
    printHelp();
    exit(0);
}

if ($cmd === 'status') {
    runStatus();
    exit(0);
}

if ($cmd === 'list') {
    runList((int) ($opts['limit'] ?? 15));
    exit(0);
}

$interviewId = $opts['interview'] ?? $opts['interview-id'] ?? '';
if ($interviewId === '') {
    fwrite(STDERR, "Missing --interview=<uuid> (use \"list\" to pick one).\n");
    exit(1);
}

$ctx = loadInterviewReportContext($interviewId);
if (!$ctx) {
    fwrite(STDERR, "No interview report found for id {$interviewId}\n");
    exit(1);
}

if ($cmd === 'preview') {
    runPreview($ctx);
    exit(0);
}

if ($cmd === 'send') {
    $mode = isset($opts['mode']) ? strtolower($opts['mode']) : null;
    if ($mode !== null && !in_array($mode, ['live', 'test', 'log'], true)) {
        fwrite(STDERR, "Invalid --mode (use live, test, or log).\n");
        exit(1);
    }
    if ($mode === 'live') {
        echo "WARNING: --mode=live sends to real hiring managers / interviewers.\n";
    }
    runSend($ctx, $mode);
    exit(0);
}

fwrite(STDERR, "Unknown command: {$cmd}\n");
printHelp();
exit(1);

function printHelp()
{
    echo "Usage:\n";
    echo "  php scripts/test_report_notifications.php status\n";
    echo "  php scripts/test_report_notifications.php list [--limit=20]\n";
    echo "  php scripts/test_report_notifications.php preview --interview=<uuid>\n";
    echo "  php scripts/test_report_notifications.php send --interview=<uuid> [--mode=test|log|live]\n";
}

function parseCliOpts(array $argv)
{
    $opts = [];
    foreach ($argv as $arg) {
        if (strpos($arg, '--') !== 0) {
            continue;
        }
        $arg = substr($arg, 2);
        if (strpos($arg, '=') !== false) {
            list($k, $v) = explode('=', $arg, 2);
            $opts[$k] = $v;
        }
    }

    return $opts;
}

function runStatus()
{
    $delivery = new MailDeliveryService();
    $mail = new MailService();
    echo "Report notification check\n";
    echo "=========================\n";
    echo 'APP_URL: ' . config('app')['url'] . "\n";
    echo 'Delivery: ' . $delivery->describeMode() . "\n";
    echo 'SMTP: ' . ($mail->isConfigured() ? 'configured' : 'NOT configured') . "\n";
    echo 'notify_report_ready: ' . (AppSettingsService::getBool('notify_report_ready', true) ? 'on' : 'off') . "\n";
    echo 'interview_report_shares table: ' . (sharesTableReady() ? 'yes' : 'NO — run php database/migrate.php') . "\n";
}

function runList($limit)
{
    if ($limit < 1) {
        $limit = 15;
    }
    $rows = db()->fetchAll(
        "SELECT TOP {$limit} i.id AS interview_id, ir.id AS report_id, j.title AS job_title,
                c.first_name, c.last_name, ir.overall_score, ir.created_at
         FROM interview_reports ir
         JOIN interviews i ON i.id = ir.interview_id
         JOIN applications a ON a.id = i.application_id
         JOIN candidates c ON c.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         ORDER BY ir.created_at DESC"
    );
    if (empty($rows)) {
        echo "No reports found.\n";
        return;
    }
    echo "Recent reports (use --interview=<interview_id>):\n\n";
    foreach ($rows as $r) {
        $name = trim($r['first_name'] . ' ' . ($r['last_name'] ?? ''));
        echo $r['interview_id'] . " | {$name} | {$r['job_title']} | score " . ($r['overall_score'] ?? '–') . "\n";
    }
}

function loadInterviewReportContext($interviewId)
{
    return db()->fetch(
        'SELECT i.id AS interview_id, ir.id AS report_id, j.id AS job_id, j.title AS job_title,
                c.first_name, c.last_name, ir.overall_score, ir.score_label
         FROM interviews i
         JOIN applications a ON a.id = i.application_id
         JOIN jobs j ON j.id = a.job_id
         JOIN candidates c ON c.id = a.candidate_id
         JOIN interview_reports ir ON ir.interview_id = i.id
         WHERE i.id = ?',
        [$interviewId]
    );
}

function runPreview(array $ctx)
{
    if (!sharesTableReady()) {
        fwrite(STDERR, "Run migration 019 first: php database/migrate.php\n");
        exit(1);
    }
    $delivery = new MailDeliveryService();
    $emailCtx = (new ReportReadyEmailService())->buildContext($ctx['interview_id']);

    echo "Preview — {$ctx['first_name']} {$ctx['last_name']} ({$ctx['job_title']})\n";
    echo "Report: {$ctx['report_id']}\n";
    echo "Delivery mode (env): " . $delivery->describeMode() . "\n\n";

    if (!$emailCtx) {
        echo "Cannot send — add at least one interviewer on the job (TO).\n";
        return;
    }

    echo "Subject: {$emailCtx['subject']}\n";
    echo 'TO: ' . implode(', ', $emailCtx['to']) . "\n";
    echo 'CC: ' . (empty($emailCtx['cc']) ? '—' : implode(', ', $emailCtx['cc'])) . "\n";
    echo "Report link: {$emailCtx['template_data']['report_url']}\n";
}

function runSend(array $ctx, $modeOverride)
{
    if (!sharesTableReady()) {
        fwrite(STDERR, "Run migration 019 first: php database/migrate.php\n");
        exit(1);
    }
    if (!AppSettingsService::getBool('notify_report_ready', true)) {
        echo "notify_report_ready is off in app settings — CLI send still runs (force).\n";
    }

    $notify = new NotificationService();
    if ($modeOverride !== null) {
        $notify->withDeliveryMode($modeOverride);
        echo "Using delivery mode override: {$modeOverride}\n";
    } else {
        echo 'Using NOTIFY_EMAIL_MODE from .env: ' . (new MailDeliveryService())->describeMode() . "\n";
    }

    $notify->notifyReportReady($ctx['interview_id'], true);
    echo "notifyReportReady finished. Check notification_log and inbox.\n";
}

function sharesTableReady()
{
    try {
        db()->fetch('SELECT TOP 1 id FROM interview_report_shares');
        return true;
    } catch (Exception $e) {
        return false;
    }
}
