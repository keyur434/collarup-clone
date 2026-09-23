<?php

/**
 * Remove leftover interview scratch files under storage/temp/.
 *
 *   php scripts/cleanup_temp_media.php --dry-run
 *   php scripts/cleanup_temp_media.php
 *   php scripts/cleanup_temp_media.php --interview-id=<uuid>
 */

require __DIR__ . '/../app/bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);
$interviewId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--interview-id=') === 0) {
        $interviewId = substr($arg, strlen('--interview-id='));
    }
}

$dir = BASE_PATH . '/storage/temp/';
if (!is_dir($dir)) {
    echo "No temp directory.\n";
    exit(0);
}

$files = [];
if ($interviewId !== null && $interviewId !== '') {
    $prefix = preg_replace('/[^a-zA-Z0-9\-]/', '', $interviewId);
    $files = glob($dir . $prefix . '_*') ?: [];
} else {
    $files = glob($dir . '*') ?: [];
    $files = array_filter($files, function ($f) {
        return is_file($f) && preg_match('/[a-f0-9\-]{36}_/i', basename($f));
    });
}

if (!$files) {
    echo "Nothing to clean.\n";
    exit(0);
}

$total = 0;
foreach ($files as $file) {
    $size = filesize($file);
    $total += $size;
    echo ($dryRun ? '[dry-run] ' : '') . basename($file) . ' (' . round($size / 1048576, 1) . " MB)\n";
    if (!$dryRun) {
        @unlink($file);
    }
}

echo ($dryRun ? 'Would remove ' : 'Removed ') . count($files) . ' file(s), ~' . round($total / 1048576, 1) . " MB\n";
