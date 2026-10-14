<?php

require __DIR__ . '/../app/bootstrap.php';

$queue = new QueueService();
$healed = 0;
$processed = $queue->processAll(10);
try {
    $healed = InterviewWorkflowService::healStuckProcessingInterviews(5);
} catch (Exception $e) {
}

$teams = new TeamsRecordingService();
$polled = $teams->pollAndQueueDueRecordings(15);

echo date('Y-m-d H:i:s') . " - Processed $processed jobs, healed $healed stuck interview(s), queued $polled Teams recording fetch(es)\n";
