<?php

return [
    'driver' => getenv('DB_DRIVER') ?: 'sqlsrv',
    'host' => getenv('DB_HOST') ?: 'srvdb',
    'port' => getenv('DB_PORT') ?: '1433',
    'database' => getenv('DB_DATABASE') ?: 'collarup_clone',
    'username' => getenv('DB_USERNAME') ?: 'sa',
    'password' => getenv('DB_PASSWORD') ?: '',
];
