<?php

require __DIR__ . '/../../app/bootstrap.php';

$blobName = isset($_GET['f']) ? $_GET['f'] : '';
$blobName = str_replace('\\', '/', $blobName);
$blobName = ltrim($blobName, '/');

if ($blobName === '' || strpos($blobName, '..') !== false) {
    http_response_code(404);
    exit;
}

$baseDir = realpath(BASE_PATH . '/storage/videos');
$path = realpath(BASE_PATH . '/storage/videos/' . $blobName);

if ($path === false || $baseDir === false || strpos($path, $baseDir) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = [
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'mkv' => 'video/x-matroska',
];
header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
readfile($path);
