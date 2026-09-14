<?php
/**
 * Validate Azure OpenAI, Graph/OneDrive, and SSO login app configuration.
 *
 * Usage:
 *   php scripts/test_integrations.php openai
 *   php scripts/test_integrations.php graph
 *   php scripts/test_integrations.php login
 *   php scripts/test_integrations.php all
 */
require __DIR__ . '/../app/bootstrap.php';

$section = isset($argv[1]) ? strtolower($argv[1]) : 'all';
$sections = $section === 'all' ? ['openai', 'login', 'graph'] : [$section];

echo "CollarUp integration checks\n";
echo "===========================\n\n";

foreach ($sections as $name) {
    if ($name === 'openai') {
        testOpenAi();
    } elseif ($name === 'login') {
        testLoginApp();
    } elseif ($name === 'graph') {
        testGraphSync();
    } else {
        echo "Unknown section: {$name}\n";
    }
    echo "\n";
}

function ok($msg)
{
    echo "[OK] {$msg}\n";
}

function warn($msg)
{
    echo "[WARN] {$msg}\n";
}

function fail($msg)
{
    echo "[FAIL] {$msg}\n";
}

function envVal($key)
{
    $v = getenv($key);
    return $v === false ? '' : trim($v);
}

function testOpenAi()
{
    echo "-- Azure OpenAI (gpt-4.1 / Foundry) --\n";
    $required = ['AZURE_OPENAI_ENDPOINT', 'AZURE_OPENAI_API_KEY', 'AZURE_OPENAI_DEPLOYMENT'];
    foreach ($required as $key) {
        if (envVal($key) === '') {
            fail("Missing {$key}");
        } else {
            ok("{$key} set");
        }
    }

    foreach (['AZURE_OPENAI_EXTRACTION_DEPLOYMENT', 'AZURE_OPENAI_REFINE_DEPLOYMENT'] as $key) {
        if (envVal($key) === '') {
            warn("Missing {$key} — will fall back to defaults");
        } else {
            ok("{$key}=" . envVal($key));
        }
    }

    if (envVal('AZURE_OPENAI_API_MODE') === '') {
        warn('AZURE_OPENAI_API_MODE not set — using auto (v1 then classic)');
    }
    if (envVal('AZURE_OPENAI_ENDPOINT_FALLBACKS') === '') {
        warn('No AZURE_OPENAI_ENDPOINT_FALLBACKS — only primary endpoint tried');
    }

    $svc = new AzureOpenAIService();
    if (!$svc->isConfigured()) {
        fail('AzureOpenAIService not configured');
        return;
    }

    try {
        $reply = $svc->ping();
        ok('Chat completion: ' . substr(trim($reply), 0, 120));
    } catch (Exception $e) {
        fail('Chat completion: ' . $e->getMessage());
        echo "       Tip: deploy gpt-4.1 + gpt-4.1-mini on Foundry; set endpoint to\n";
        echo "       https://emailintigration.services.ai.azure.com\n";
    }

    try {
        $svc->ping(envVal('AZURE_OPENAI_REFINE_DEPLOYMENT') ?: 'gpt-4.1-mini');
        ok('Refine deployment reachable');
    } catch (Exception $e) {
        fail('Refine deployment: ' . $e->getMessage());
    }
}

function testLoginApp()
{
    echo "-- Azure AD login app (SSO) --\n";
    $keys = [
        'AZURE_AD_TENANT_ID',
        'AZURE_AD_LOGIN_CLIENT_ID',
        'AZURE_AD_LOGIN_CLIENT_SECRET',
        'AZURE_AD_LOGIN_REDIRECT_URI',
    ];
    $missing = 0;
    foreach ($keys as $key) {
        if (envVal($key) === '') {
            fail("Missing {$key}");
            $missing++;
        } else {
            ok("{$key} set");
        }
    }
    if ($missing > 0) {
        return;
    }

    $redirect = envVal('AZURE_AD_LOGIN_REDIRECT_URI');
    $expected = rtrim(config('app')['url'], '/') . '/login/callback';
    if ($redirect !== $expected) {
        warn("Redirect URI mismatch. .env: {$redirect}");
        warn("Expected from APP_URL: {$expected}");
        warn('Must match Azure portal → Login app → Authentication → Web redirect URI');
    } else {
        ok('Redirect URI matches APP_URL/login/callback');
    }

    $azure = new AzureAdAuthService();
    if ($azure->isConfigured()) {
        ok('AzureAdAuthService configured — test in browser: /login → Sign in with Microsoft');
    } else {
        fail('AzureAdAuthService not configured');
    }
}

function testGraphSync()
{
    echo "-- Graph sync app (OneDrive / Teams) --\n";
    $keys = [
        'AZURE_AD_TENANT_ID',
        'AZURE_AD_SYNC_CLIENT_ID',
        'AZURE_AD_SYNC_CLIENT_SECRET',
        'ONEDRIVE_DRIVE_ID',
    ];
    $missing = 0;
    foreach ($keys as $key) {
        if (envVal($key) === '') {
            fail("Missing {$key}");
            $missing++;
        } else {
            ok("{$key} set");
        }
    }

    $folder = envVal('ONEDRIVE_FOLDER_PATH');
    if ($folder === '') {
        warn('ONEDRIVE_FOLDER_PATH empty — default /InterviewRecordings used');
    } else {
        ok('ONEDRIVE_FOLDER_PATH=' . $folder);
    }

    if ($missing > 0) {
        echo "       Create a SEPARATE app registration (Sync app) — not the login app.\n";
        echo "       Grant admin consent: Files.ReadWrite.All, Sites.Read.All\n";
        return;
    }

    $graph = new MicrosoftGraphService('sync');
    if (!$graph->isConfigured()) {
        fail('MicrosoftGraphService sync app not configured');
        return;
    }

    try {
        $token = $graph->getAccessToken();
        if ($token) {
            ok('Graph token acquired');
        }
    } catch (Exception $e) {
        fail('Graph token: ' . $e->getMessage());
        return;
    }

    $driveId = envVal('ONEDRIVE_DRIVE_ID');
    try {
        $info = $graph->request('GET', '/drives/' . $driveId);
        $name = isset($info['name']) ? $info['name'] : (isset($info['driveType']) ? $info['driveType'] : 'drive');
        ok('OneDrive drive reachable: ' . $name);
    } catch (Exception $e) {
        fail('OneDrive drive: ' . $e->getMessage());
        echo "       Get drive id: GET https://graph.microsoft.com/v1.0/users/{email}/drive\n";
    }

    $storage = new OneDriveStorageService();
    if ($storage->isConfigured()) {
        ok('OneDriveStorageService ready');
    } else {
        fail('OneDriveStorageService not fully configured');
    }
}
