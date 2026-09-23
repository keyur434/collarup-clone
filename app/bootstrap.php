<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Helpers/functions.php';
env_load(BASE_PATH . '/.env');

date_default_timezone_set(config('app')['timezone']);

session_name(config('app')['session_name']);
session_configure();
session_start();
if (!empty($_SESSION['csrf_token'])) {
    csrf_sync_cookie($_SESSION['csrf_token']);
}

require BASE_PATH . '/app/Config/Database.php';
require BASE_PATH . '/app/Config/Router.php';

spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . '/app/Controllers/' . $class . '.php',
        BASE_PATH . '/app/Models/' . $class . '.php',
        BASE_PATH . '/app/Services/' . $class . '.php',
        BASE_PATH . '/app/Middleware/' . $class . '.php',
        BASE_PATH . '/app/Jobs/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

if (config('app')['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
