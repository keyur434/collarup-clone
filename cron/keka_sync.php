<?php

require __DIR__ . '/../app/bootstrap.php';

$keka = new KekaService();
if (!$keka->isConfigured()) {
    echo date('Y-m-d H:i:s') . " - Keka not configured\n";
    exit(0);
}

try {
    $result = $keka->syncEmployees();
    echo date('Y-m-d H:i:s') . ' - Keka synced ' . (int) $result['synced'] . " employees\n";
} catch (Exception $e) {
    echo date('Y-m-d H:i:s') . ' - Keka sync failed: ' . $e->getMessage() . "\n";
    exit(1);
}
