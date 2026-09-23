<?php
/**
 * Resolve Graph drive ID for OneDrive / SharePoint setup.
 *
 * Usage:
 *   php scripts/resolve_onedrive_drive.php user@company.com
 *   php scripts/resolve_onedrive_drive.php --site https://tenant.sharepoint.com/sites/HR
 */
require __DIR__ . '/../app/bootstrap.php';

$arg = isset($argv[1]) ? trim($argv[1]) : '';
if ($arg === '' || $arg === '--help') {
    echo <<<HELP
Resolve ONEDRIVE_DRIVE_ID for .env

  php scripts/resolve_onedrive_drive.php user@company.com
      → personal OneDrive for that user

  php scripts/resolve_onedrive_drive.php --site https://tenant.sharepoint.com/sites/YourSite
      → list document libraries (drives) on a SharePoint site

Then set in .env:
  ONEDRIVE_DRIVE_ID=<id>
  ONEDRIVE_FOLDER_PATH=InterviewRecordings

HELP;
    exit($arg === '--help' ? 0 : 1);
}

$graph = new MicrosoftGraphService('sync');
if (!$graph->isConfigured()) {
    fwrite(STDERR, "Sync app not configured (AZURE_AD_SYNC_*).\n");
    exit(1);
}

try {
    $graph->getAccessToken();
} catch (Exception $e) {
    fwrite(STDERR, "Token failed: " . $e->getMessage() . "\n");
    exit(1);
}

if (strpos($arg, '--site') === 0) {
    $siteUrl = isset($argv[2]) ? trim($argv[2]) : '';
    if ($siteUrl === '') {
        fwrite(STDERR, "Provide site URL after --site\n");
        exit(1);
    }
    $parsed = parse_url($siteUrl);
    $host = $parsed['host'] ?? '';
    $path = isset($parsed['path']) ? $parsed['path'] : '';
    if ($host === '' || strpos($path, '/sites/') === false) {
        fwrite(STDERR, "URL must be like https://tenant.sharepoint.com/sites/SiteName\n");
        exit(1);
    }
    $site = $graph->request('GET', '/sites/' . $host . ':' . $path);
    $drives = $graph->request('GET', '/sites/' . $site['id'] . '/drives');
    echo "Drives on site:\n\n";
    foreach ($drives['value'] ?? [] as $d) {
        echo ($d['name'] ?? '?') . "\n";
        echo "  id: " . ($d['id'] ?? '') . "\n";
        echo "  webUrl: " . ($d['webUrl'] ?? '') . "\n\n";
    }
    exit(0);
}

$email = $arg;
$user = $graph->request('GET', '/users/' . rawurlencode($email));
$drive = $graph->request('GET', '/users/' . rawurlencode($user['id']) . '/drive');
echo "User: " . ($user['displayName'] ?? $email) . "\n";
echo "ONEDRIVE_DRIVE_ID=" . ($drive['id'] ?? '') . "\n";
echo "Drive type: " . ($drive['driveType'] ?? '') . "\n";
echo "Web URL: " . ($drive['webUrl'] ?? '') . "\n";
echo "\nCreate folder InterviewRecordings in this drive (or set ONEDRIVE_FOLDER_PATH).\n";
