<?php
/**
 * MSSQL setup script. Run: php database/install.php
 * Requires: PHP pdo_sqlsrv extension + sqlcmd (optional)
 */
require __DIR__ . '/../app/bootstrap.php';

echo "CollarUp Clone - MSSQL Database Setup\n";
echo "=====================================\n\n";

$config = config('database');
$server = $config['host'] . (!empty($config['port']) ? ',' . $config['port'] : '');

try {
    $pdo = new PDO(
        'sqlsrv:Server=' . $server,
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\nEnsure pdo_sqlsrv extension is enabled.\n");
}

function runSqlFile($pdo, $filepath)
{
    $sql = file_get_contents($filepath);
    $batches = preg_split('/^\s*GO\s*$/mi', $sql);
    foreach ($batches as $batch) {
        $batch = trim($batch);
        if ($batch !== '') {
            try {
                $pdo->exec($batch);
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'already exists') === false && strpos($msg, 'There is already') === false) {
                    echo "Warning: $msg\n";
                }
            }
        }
    }
}

echo "Running schema.sql...\n";
runSqlFile($pdo, __DIR__ . '/schema.sql');

$pdo = new PDO(
    'sqlsrv:Server=' . $server . ';Database=' . $config['database'],
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "Running seeds.sql...\n";
runSqlFile($pdo, __DIR__ . '/seeds.sql');

$migrations = glob(__DIR__ . '/migrations/*.sql');
sort($migrations);
foreach ($migrations as $migration) {
    echo 'Running migration ' . basename($migration) . "...\n";
    runSqlFile($pdo, $migration);
}

$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@company.local'");
$stmt->execute([$hash]);

echo "\nDone! Database: {$config['database']}\n";
echo "Optional: php database/seed_locations.php for full India states/cities\n";
echo "Login: admin@company.local / admin123\n";
