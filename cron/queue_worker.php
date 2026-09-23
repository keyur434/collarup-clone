<?php

require __DIR__ . '/../app/bootstrap.php';

$queue = new QueueService();
$processed = $queue->processAll(10);

$teams = new TeamsRecordingService();
$polled = $teams->pollAndQueueDueRecordings(15);

echo date('Y-m-d H:i:s') . " - Processed $processed jobs, queued $polled Teams recording fetch(es)\n";
