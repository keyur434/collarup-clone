<?php
/**
 * Recompute recording_fetch_after for pending interviews (after timing logic change).
 *   php scripts/refresh_recording_fetch_schedule.php
 */
require __DIR__ . '/../app/bootstrap.php';

$teams = new TeamsRecordingService();
$rows = db()->fetchAll(
    "SELECT id, scheduled_date, scheduled_start, scheduled_end
     FROM interviews
     WHERE recording_fetch_state IN ('waiting', 'retry')
       AND scheduled_date IS NOT NULL"
);
$n = 0;
foreach ($rows as $r) {
    $when = $teams->computeRecordingFetchAfter($r['scheduled_date'], $r['scheduled_start'], $r['scheduled_end']);
    db()->update('interviews', ['recording_fetch_after' => $when], 'id = :id', ['id' => $r['id']]);
    $n++;
}
echo "Updated recording_fetch_after for {$n} interview(s).\n";
echo "Next: php cron/queue_worker.php\n";
