<?php

/**
 * Run Teams recording fetch once (no queue). Shows the real Graph error.
 *
 *   php scripts/fetch_teams_recording_now.php 0ef3803f-7993-4dd7-9b89-87624d2078ee
 */

require __DIR__ . '/../app/bootstrap.php';

$id = isset($argv[1]) ? trim($argv[1]) : '';
if ($id === '') {
    fwrite(STDERR, "Usage: php scripts/fetch_teams_recording_now.php <interview-id>\n");
    exit(1);
}

$teams = new TeamsRecordingService();
if (!$teams->graphReady()) {
    fwrite(STDERR, "Graph sync app not configured.\n");
    exit(1);
}

try {
    $teams->fetchForInterview($id);
    echo "OK — recording downloaded and stored. Run: php cron/queue_worker.php (for AnalyzeInterview)\n";
    InterviewWorkflowService::markProcessing($id);
    (new QueueService())->push('AnalyzeInterview', ['interview_id' => $id]);
    echo "Queued AnalyzeInterview. If queue_worker shows 0 jobs, run: php scripts/requeue_analyze.php {$id}\n";
} catch (RecordingNotReadyException $e) {
    echo "Not ready: " . $e->getMessage() . "\n";
    echo "Common: meeting not indexed yet, no cloud recording, or wrong host email.\n";
    exit(2);
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
    exit(1);
}
