<?php

/**
 * List Teams cloud recordings for an organizer on a day (Graph getAllRecordings).
 *
 *   php scripts/probe_teams_recordings.php hr@yourcompany.com 2026-09-17
 */

require __DIR__ . '/../app/bootstrap.php';

$email = isset($argv[1]) ? trim($argv[1]) : '';
$day = isset($argv[2]) ? trim($argv[2]) : date('Y-m-d');
if ($email === '') {
    fwrite(STDERR, "Usage: php scripts/probe_teams_recordings.php <organizer-email> [YYYY-MM-DD]\n");
    exit(1);
}

$teams = new TeamsRecordingService();
if (!$teams->graphReady()) {
    fwrite(STDERR, "Graph sync not configured.\n");
    exit(1);
}

$graph = new MicrosoftGraphService('sync');
$user = $graph->request('GET', '/users/' . rawurlencode($email));
$userId = $user['id'];
echo "Organizer: {$email} ({$userId})\nDay: {$day}\n\n";

$tz = new DateTimeZone(config('app')['timezone']);
$utc = new DateTimeZone('UTC');
$start = new DateTime($day . ' 00:00:00', $tz);
$end = new DateTime($day . ' 23:59:59', $tz);
$start->setTimezone($utc);
$end->setTimezone($utc);
$rangeStart = $start->format('Y-m-d\TH:i:s\Z');
$rangeEnd = $end->format('Y-m-d\TH:i:s\Z');

try {
    $safeUser = str_replace("'", "''", $userId);
    $path = '/users/' . rawurlencode($userId)
        . "/onlineMeetings/getAllRecordings(meetingOrganizerUserId='{$safeUser}',"
        . 'startDateTime=' . $rangeStart . ',endDateTime=' . $rangeEnd . ')';
    $result = $graph->request('GET', $path);
    $items = isset($result['value']) ? $result['value'] : [];
    echo 'Recordings returned: ' . count($items) . PHP_EOL;
    foreach ($items as $i => $rec) {
        echo ($i + 1) . '. id=' . ($rec['id'] ?? '?')
            . ' meetingId=' . substr($rec['meetingId'] ?? '', 0, 40) . '…'
            . ' created=' . ($rec['createdDateTime'] ?? '?')
            . ' end=' . ($rec['endDateTime'] ?? '?') . PHP_EOL;
        if (!empty($rec['recordingContentUrl'])) {
            echo '   recordingContentUrl=' . substr($rec['recordingContentUrl'], 0, 120) . '…' . PHP_EOL;
        }
    }
    if (empty($items)) {
        echo "\nIf 0: no cloud recording, wrong organizer, or missing Teams application access policy for the sync app.\n";
    }
} catch (Exception $e) {
    echo 'Graph error: ' . $e->getMessage() . PHP_EOL;
    echo "Often fixed by Teams admin: application access policy granting this app access to the organizer's meetings.\n";
    exit(1);
}
