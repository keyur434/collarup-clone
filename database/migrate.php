<?php
/**
 * Apply incremental migrations. Run: php database/migrate.php
 */
require __DIR__ . '/../app/bootstrap.php';

echo "CollarUp Clone - Database Migrations\n";
echo "====================================\n\n";

$config = config('database');
$server = $config['host'] . (!empty($config['port']) ? ',' . $config['port'] : '');

$pdo = new PDO(
    'sqlsrv:Server=' . $server . ';Database=' . $config['database'],
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $filepath) {
    echo 'Running ' . basename($filepath) . "...\n";
    $sql = file_get_contents($filepath);
    $batches = preg_split('/^\s*GO\s*$/mi', $sql);
    foreach ($batches as $batch) {
        $batch = trim($batch);
        if ($batch === '') {
            continue;
        }
        try {
            $pdo->exec($batch);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'already exists') === false && strpos($msg, 'There is already') === false) {
                echo "  Warning: $msg\n";
            }
        }
    }
}

echo "\nMigrations complete.\n";
