<?php
/**
 * Backfill company_about in app_settings when empty. Run: php database/migrations/010_company_about_seed.php
 */
require __DIR__ . '/../../app/bootstrap.php';

$about = company_about_default();
if ($about === '') {
    echo "No usable company_about.txt (empty or placeholder only).\n";
    exit(1);
}

$existing = AppSettingsService::get('company_about', null);
if ($existing !== null && trim($existing) !== '' && !company_about_is_setup_instructions($existing)) {
    echo "company_about already set in app_settings — skipped.\n";
    exit(0);
}

AppSettingsService::set('company_about', $about);
echo "company_about saved to app_settings (" . strlen($about) . " chars).\n";
