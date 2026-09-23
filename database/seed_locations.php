<?php
/**
 * Seed / audit Indian states and cities (36 states/UTs, ~4200 cities).
 *
 * Data: database/data/india_locations.json (from dr5hn/countries-states-cities-database)
 *
 * Usage:
 *   php database/seed_locations.php           # import missing rows
 *   php database/seed_locations.php --audit   # compare DB vs dataset
 *   php database/seed_locations.php --fetch   # refresh JSON from GitHub (needs network)
 */
require __DIR__ . '/../app/bootstrap.php';

$opts = getopt('', ['audit', 'fetch', 'help']);

if (isset($opts['help'])) {
    echo <<<HELP
India locations seeder

  php database/seed_locations.php           Add missing states/cities (idempotent)
  php database/seed_locations.php --audit   Show gaps vs full India dataset
  php database/seed_locations.php --fetch   Re-download dataset into database/data/

HELP;
    exit(0);
}

$dataFile = __DIR__ . '/data/india_locations.json';
$sourceUrl = 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/countries%2Bstates%2Bcities.json';

if (isset($opts['fetch'])) {
    echo "Downloading India locations from dr5hn dataset...\n";
    $json = http_get($sourceUrl);
    if ($json === null) {
        fwrite(STDERR, "Download failed.\n");
        exit(1);
    }
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        fwrite(STDERR, "Invalid JSON from source.\n");
        exit(1);
    }
    $india = null;
    foreach ($payload as $country) {
        if (isset($country['name']) && $country['name'] === 'India') {
            $india = $country;
            break;
        }
    }
    if (!$india || empty($india['states'])) {
        fwrite(STDERR, "India block not found in source.\n");
        exit(1);
    }
    $out = [
        'source' => 'https://github.com/dr5hn/countries-states-cities-database',
        'country' => 'India',
        'iso2' => 'IN',
        'generated_at' => date('Y-m-d'),
        'states' => [],
    ];
    foreach ($india['states'] as $st) {
        if (empty($st['name'])) {
            continue;
        }
        $cities = [];
        foreach ($st['cities'] ?? [] as $city) {
            $name = is_array($city) ? ($city['name'] ?? '') : (string) $city;
            $name = trim($name);
            if ($name !== '') {
                $cities[$name] = true;
            }
        }
        $cityList = array_keys($cities);
        sort($cityList, SORT_STRING);
        $out['states'][] = [
            'name' => $st['name'],
            'cities' => $cityList,
        ];
    }
    usort($out['states'], function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    if (!is_dir(dirname($dataFile))) {
        mkdir(dirname($dataFile), 0755, true);
    }
    file_put_contents($dataFile, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Saved {$dataFile}\n";
}

if (!is_readable($dataFile)) {
    fwrite(STDERR, "Missing {$dataFile}. Run with --fetch first.\n");
    exit(1);
}

$dataset = json_decode(file_get_contents($dataFile), true);
if (empty($dataset['states']) || !is_array($dataset['states'])) {
    fwrite(STDERR, "Invalid dataset file.\n");
    exit(1);
}

$config = config('database');
$server = $config['host'] . (!empty($config['port']) ? ',' . $config['port'] : '');
$pdo = new PDO(
    'sqlsrv:Server=' . $server . ';Database=' . $config['database'],
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$indiaId = $pdo->query("SELECT id FROM countries WHERE code = 'IN' OR name = 'India'")->fetchColumn();
if (!$indiaId) {
    $pdo->exec("INSERT INTO countries (name, code) VALUES (N'India', N'IN')");
    $indiaId = $pdo->lastInsertId();
    echo "Created country India (id {$indiaId})\n";
}

$dbStates = $pdo->prepare('SELECT id, name FROM states WHERE country_id = ?');
$dbStates->execute([$indiaId]);
$stateByName = [];
foreach ($dbStates->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $stateByName[normalize_location_key($row['name'])] = $row;
}

$expectedStateNames = [];
foreach ($dataset['states'] as $st) {
    $expectedStateNames[normalize_location_key($st['name'])] = $st['name'];
}

if (isset($opts['audit'])) {
    run_audit($pdo, $indiaId, $dataset, $stateByName, $expectedStateNames);
    exit(0);
}

$insertState = $pdo->prepare('INSERT INTO states (country_id, name) VALUES (?, ?)');
$stateIdStmt = $pdo->prepare('SELECT id FROM states WHERE country_id = ? AND name = ?');
$cityExists = $pdo->prepare('SELECT id FROM cities WHERE state_id = ? AND name = ?');
$insertCity = $pdo->prepare('INSERT INTO cities (state_id, name) VALUES (?, ?)');

$statesAdded = 0;
$citiesAdded = 0;

foreach ($dataset['states'] as $st) {
    $stateName = trim($st['name']);
    if ($stateName === '') {
        continue;
    }
    $key = normalize_location_key($stateName);
    $stateId = null;
    if (isset($stateByName[$key])) {
        $stateId = (int) $stateByName[$key]['id'];
    } else {
        $stateIdStmt->execute([$indiaId, $stateName]);
        $stateId = $stateIdStmt->fetchColumn();
        if (!$stateId) {
            $insertState->execute([$indiaId, $stateName]);
            $stateId = (int) $pdo->lastInsertId();
            $stateByName[$key] = ['id' => $stateId, 'name' => $stateName];
            $statesAdded++;
        }
    }

    foreach ($st['cities'] as $cityName) {
        $cityName = trim($cityName);
        if ($cityName === '') {
            continue;
        }
        $cityExists->execute([$stateId, $cityName]);
        if (!$cityExists->fetchColumn()) {
            $insertCity->execute([$stateId, $cityName]);
            $citiesAdded++;
        }
    }
}

echo "India location seed complete.\n";
echo "  States added: {$statesAdded}\n";
echo "  Cities added: {$citiesAdded}\n";
echo "  Dataset: " . count($dataset['states']) . " states/UTs, "
    . array_sum(array_map(function ($s) { return count($s['cities']); }, $dataset['states'])) . " cities\n";
echo "Run with --audit to verify coverage.\n";

function normalize_location_key($name)
{
    return strtolower(preg_replace('/\s+/', ' ', trim($name)));
}

function http_get($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 300) {
            return $body;
        }
    }
    $ctx = stream_context_create(['http' => ['timeout' => 300]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body !== false ? $body : null;
}

function run_audit($pdo, $indiaId, $dataset, $stateByName, $expectedStateNames)
{
    echo "=== India location audit ===\n\n";

    $missingStates = [];
    foreach ($expectedStateNames as $key => $label) {
        if (!isset($stateByName[$key])) {
            $missingStates[] = $label;
        }
    }

    $extraStates = [];
    foreach ($stateByName as $key => $row) {
        if (!isset($expectedStateNames[$key])) {
            $extraStates[] = $row['name'];
        }
    }

    echo 'States in dataset: ' . count($expectedStateNames) . "\n";
    echo 'States in DB (India): ' . count($stateByName) . "\n";

    if ($missingStates) {
        echo "\nMissing states/UTs in DB:\n";
        foreach ($missingStates as $name) {
            echo "  - {$name}\n";
        }
    } else {
        echo "\nAll 36 states/UTs present in DB.\n";
    }

    if ($extraStates) {
        echo "\nExtra state names in DB (not in dataset — legacy/custom):\n";
        foreach ($extraStates as $name) {
            echo "  - {$name}\n";
        }
    }

    $cityCountStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM cities ci JOIN states s ON s.id = ci.state_id WHERE s.country_id = ?'
    );
    $cityCountStmt->execute([$indiaId]);
    $dbCityCount = (int) $cityCountStmt->fetchColumn();
    $expectedCities = 0;
    foreach ($dataset['states'] as $st) {
        $expectedCities += count($st['cities']);
    }

    echo "\nCities in dataset: {$expectedCities}\n";
    echo "Cities in DB (India): {$dbCityCount}\n";

    if ($dbCityCount < $expectedCities) {
        echo "\nRun without --audit to import missing cities.\n";
    }
}
