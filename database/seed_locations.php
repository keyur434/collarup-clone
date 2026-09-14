<?php
/**
 * Import Indian states and major cities. Run: php database/seed_locations.php
 */
require __DIR__ . '/../app/bootstrap.php';

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
}

$locations = [
    'Andhra Pradesh' => ['Visakhapatnam', 'Vijayawada', 'Guntur', 'Nellore', 'Tirupati'],
    'Arunachal Pradesh' => ['Itanagar', 'Naharlagun'],
    'Assam' => ['Guwahati', 'Dibrugarh', 'Silchar', 'Jorhat'],
    'Bihar' => ['Patna', 'Gaya', 'Muzaffarpur', 'Bhagalpur'],
    'Chhattisgarh' => ['Raipur', 'Bhilai', 'Bilaspur', 'Korba'],
    'Goa' => ['Panaji', 'Margao', 'Vasco da Gama'],
    'Gujarat' => ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Gandhinagar', 'Bhavnagar', 'Ankleshwar', 'Dahej'],
    'Haryana' => ['Gurugram', 'Faridabad', 'Panipat', 'Ambala', 'Jhajjar', 'Karnal', 'Rohtak'],
    'Himachal Pradesh' => ['Shimla', 'Dharamshala', 'Solan'],
    'Jharkhand' => ['Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro'],
    'Karnataka' => ['Bengaluru', 'Mysuru', 'Mangaluru', 'Hubballi'],
    'Kerala' => ['Thiruvananthapuram', 'Kochi', 'Kozhikode', 'Thrissur'],
    'Madhya Pradesh' => ['Bhopal', 'Indore', 'Jabalpur', 'Gwalior', 'Ujjain'],
    'Maharashtra' => ['Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Thane', 'Aurangabad'],
    'Manipur' => ['Imphal'],
    'Meghalaya' => ['Shillong'],
    'Mizoram' => ['Aizawl'],
    'Nagaland' => ['Kohima', 'Dimapur'],
    'Odisha' => ['Bhubaneswar', 'Cuttack', 'Rourkela'],
    'Punjab' => ['Chandigarh', 'Ludhiana', 'Amritsar', 'Jalandhar', 'Mohali'],
    'Rajasthan' => ['Jaipur', 'Jodhpur', 'Udaipur', 'Kota', 'Ajmer'],
    'Sikkim' => ['Gangtok'],
    'Tamil Nadu' => ['Chennai', 'Coimbatore', 'Madurai', 'Hosur', 'Salem', 'Tiruchirappalli'],
    'Telangana' => ['Hyderabad', 'Warangal', 'Nizamabad'],
    'Tripura' => ['Agartala'],
    'Uttar Pradesh' => ['Lucknow', 'Noida', 'Ghaziabad', 'Kanpur', 'Varanasi', 'Agra'],
    'Uttarakhand' => ['Dehradun', 'Haridwar', 'Haldwani'],
    'West Bengal' => ['Kolkata', 'Howrah', 'Durgapur', 'Siliguri'],
    'Delhi' => ['New Delhi', 'Delhi'],
    'Jammu and Kashmir' => ['Srinagar', 'Jammu'],
    'Ladakh' => ['Leh'],
    'Puducherry' => ['Puducherry'],
    'Chandigarh' => ['Chandigarh'],
];

$stateStmt = $pdo->prepare('SELECT id FROM states WHERE country_id = ? AND name = ?');
$insertState = $pdo->prepare('INSERT INTO states (country_id, name) VALUES (?, ?)');
$cityStmt = $pdo->prepare('SELECT id FROM cities WHERE state_id = ? AND name = ?');
$insertCity = $pdo->prepare('INSERT INTO cities (state_id, name) VALUES (?, ?)');

$statesAdded = 0;
$citiesAdded = 0;

foreach ($locations as $stateName => $cities) {
    $stateStmt->execute([$indiaId, $stateName]);
    $stateId = $stateStmt->fetchColumn();
    if (!$stateId) {
        $insertState->execute([$indiaId, $stateName]);
        $stateId = $pdo->lastInsertId();
        $statesAdded++;
    }

    foreach ($cities as $cityName) {
        $cityStmt->execute([$stateId, $cityName]);
        if (!$cityStmt->fetchColumn()) {
            $insertCity->execute([$stateId, $cityName]);
            $citiesAdded++;
        }
    }
}

echo "Location seed complete. States added: $statesAdded, cities added: $citiesAdded\n";
