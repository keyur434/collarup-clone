<?php

/**
 *   php scripts/check_team_user.php someone@company.local
 */

require __DIR__ . '/../app/bootstrap.php';

$email = isset($argv[1]) ? strtolower(trim($argv[1])) : '';
if ($email === '') {
    fwrite(STDERR, "Usage: php scripts/check_team_user.php <email>\n");
    exit(1);
}

$row = db()->fetch('SELECT id, email, role, is_active, azure_oid, CASE WHEN password_hash IS NULL THEN 0 ELSE 1 END AS has_password FROM users WHERE LOWER(LTRIM(RTRIM(email))) = ?', [$email]);
if (!$row) {
    echo "No user row for {$email} (add in Team Management).\n";
    exit(1);
}
echo "id={$row['id']} role={$row['role']} active=" . ($row['is_active'] ? 'yes' : 'no') . " has_password=" . ($row['has_password'] ? 'yes' : 'no') . "\n";
echo "azure_oid=" . ($row['azure_oid'] ?: '(not linked yet)') . "\n";
if (!(int) $row['is_active']) {
    echo "User is inactive — re-add in Team or reactivate.\n";
}
