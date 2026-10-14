<?php

return [
    'driver' => env('DB_DRIVER', 'sqlsrv'),
    'host' => env('DB_HOST', 'srvdb'),
    'port' => env('DB_PORT', '1433'),
    'database' => env('DB_DATABASE', 'collarup_clone'),
    'username' => env('DB_USERNAME', 'sa'),
    'password' => env('DB_PASSWORD', ''),
    'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', '1') !== '0',
    'login_timeout' => (int) env('DB_LOGIN_TIMEOUT', '10'),
    'connect_retries' => (int) env('DB_CONNECT_RETRIES', '3'),
];
