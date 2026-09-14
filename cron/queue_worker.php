<?php

require __DIR__ . '/../app/bootstrap.php';

$queue = new QueueService();
$processed = $queue->processAll(10);

echo date('Y-m-d H:i:s') . " - Processed $processed jobs\n";
