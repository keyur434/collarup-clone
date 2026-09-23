<?php

/**
 * Queue AnalyzeInterview immediately (and reset stuck/failed jobs for that interview).
 *
 *   php scripts/requeue_analyze.php <interview-id>
 *   php scripts/requeue_analyze.php <interview-id> --run   # queue + run job once in this process
 */

require __DIR__ . '/../app/bootstrap.php';

$interviewId = isset($argv[1]) ? trim($argv[1]) : '';
$runNow = in_array('--run', $argv, true);

if ($interviewId === '') {
    fwrite(STDERR, "Usage: php scripts/requeue_analyze.php <interview-id> [--run]\n");
    exit(1);
}

$media = db()->fetch(
    'SELECT TOP 1 id, processing_status, blob_name FROM interview_media WHERE interview_id = ? ORDER BY id DESC',
    [$interviewId]
);
if (!$media || empty($media['blob_name'])) {
    fwrite(STDERR, "No interview_media with video for this interview. Run fetch_teams_recording_now first.\n");
    exit(1);
}

db()->query(
    "UPDATE job_queue SET status = 'failed', error_message = 'superseded by requeue'
     WHERE status IN ('pending', 'processing') AND job_type = 'AnalyzeInterview'
       AND payload LIKE ?",
    ['%' . $interviewId . '%']
);

InterviewWorkflowService::markProcessing($interviewId);
$queue = new QueueService();
$queue->push('AnalyzeInterview', ['interview_id' => $interviewId]);
echo "Queued AnalyzeInterview for {$interviewId}\n";

if ($runNow) {
    echo "Running job now...\n";
    $job = new AnalyzeInterviewJob();
    try {
        $job->handle(['interview_id' => $interviewId]);
        echo "AnalyzeInterview completed OK.\n";
    } catch (Exception $e) {
        fwrite(STDERR, "Failed: " . $e->getMessage() . "\n");
        exit(1);
    }
} else {
    echo "Next: php cron/queue_worker.php\n";
}
