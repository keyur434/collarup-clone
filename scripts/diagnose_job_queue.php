<?php

require __DIR__ . '/../app/bootstrap.php';

$interviewId = isset($argv[1]) ? trim($argv[1]) : '';

echo 'UTC now (queue clock): ' . gmdate('Y-m-d H:i:s') . PHP_EOL;
echo 'Local now: ' . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

$rows = db()->fetchAll(
    "SELECT TOP 20 id, job_type, status, attempts, max_attempts, error_message,
            available_at, started_at, completed_at, created_at, payload
     FROM job_queue
     ORDER BY id DESC"
);

if (empty($rows)) {
    echo "job_queue is empty.\n";
    exit(0);
}

foreach ($rows as $r) {
    $payload = json_decode($r['payload'], true);
    $iv = is_array($payload) && !empty($payload['interview_id']) ? $payload['interview_id'] : '';
    if ($interviewId !== '' && $iv !== $interviewId) {
        continue;
    }
    echo "#{$r['id']} {$r['job_type']} status={$r['status']} attempts={$r['attempts']}/{$r['max_attempts']}\n";
    echo "  available_at={$r['available_at']} created={$r['created_at']}\n";
    if ($iv) {
        echo "  interview_id={$iv}\n";
    }
    if (!empty($r['error_message'])) {
        echo "  error: " . substr($r['error_message'], 0, 200) . "\n";
    }
    echo "\n";
}

$due = db()->fetch(
    "SELECT COUNT(*) AS c FROM job_queue WHERE status = 'pending' AND available_at <= SYSUTCDATETIME()"
);
echo 'Pending jobs due now (UTC): ' . (int) $due['c'] . PHP_EOL;
