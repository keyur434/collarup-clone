<?php
/**
 * One-time: replace legacy setup-instruction text in app_settings with file default.
 * Run: php scripts/fix_company_about_placeholder.php
 */
require __DIR__ . '/../app/bootstrap.php';

$stored = AppSettingsService::get('company_about', null);
if ($stored === null || trim($stored) === '') {
    echo "company_about is empty — set it in Settings → Integrations or run database/migrations/010_company_about_seed.php\n";
    exit(0);
}

if (!company_about_is_setup_instructions($stored)) {
    echo "company_about is already real copy (" . strlen($stored) . " chars) — no change.\n";
    exit(0);
}

$new = company_about_default();
if ($new === '') {
    AppSettingsService::set('company_about', '');
    echo "Cleared placeholder instructions from app_settings. Add your text in Settings → Integrations.\n";
    exit(0);
}

AppSettingsService::set('company_about', $new);
echo "Replaced placeholder with default company copy (" . strlen($new) . " chars).\n";

$jobs = db()->fetchAll(
    "SELECT id FROM jobs WHERE about_company LIKE ? OR about_company LIKE ?",
    ['%Replace this text in Settings%', '%010_company_about_seed%']
);
foreach ($jobs as $row) {
    db()->update('jobs', ['about_company' => $new], 'id = :id', ['id' => $row['id']]);
}
if (!empty($jobs)) {
    echo 'Updated about_company on ' . count($jobs) . " job(s) that had placeholder text.\n";
}
