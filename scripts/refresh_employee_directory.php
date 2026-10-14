<?php

/**
 * Weekly directory refresh (Task Scheduler / cron).
 * php scripts/refresh_employee_directory.php
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Helpers/functions.php';
env_load(BASE_PATH . '/.env');
date_default_timezone_set(config('app')['timezone']);
require BASE_PATH . '/app/Config/Database.php';

spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . '/app/Services/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$cache = new EmployeeDirectoryCacheService();
if (!$cache->isAvailable()) {
    fwrite(STDERR, "Run migration 013_employee_directory_cache.sql first.\n");
    exit(1);
}

$cache->refresh();
$count = db()->fetch('SELECT COUNT(*) AS c FROM employee_directory_cache')['c'];
echo "Employee directory cache refreshed: {$count} rows.\n";
