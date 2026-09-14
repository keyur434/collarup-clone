<?php

require __DIR__ . '/../app/bootstrap.php';

if (!AppSettingsService::getBool('retention_auto_archive', true)) {
    echo date('Y-m-d H:i:s') . " - Retention auto-archive disabled\n";
    exit(0);
}

$days = AppSettingsService::getInt('retention_days_completed', 365);
if ($days < 30) {
    echo date('Y-m-d H:i:s') . " - Retention days too low ($days), skipping\n";
    exit(0);
}

$cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

$apps = db()->fetchAll(
    "SELECT a.id FROM applications a
     JOIN interviews i ON i.application_id = a.id
     WHERE a.is_archived = 0 AND a.status = 'completed' AND i.completed_at IS NOT NULL AND i.completed_at < ?",
    [$cutoff]
);

$count = 0;
foreach ($apps as $app) {
    db()->update('applications', [
        'is_archived' => 1,
        'status' => 'cancelled',
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $app['id']]);
    ActivityLogService::log('retention_archive', 'application', $app['id']);
    $count++;
}

echo date('Y-m-d H:i:s') . " - Retention archived $count application(s) older than $days days\n";
