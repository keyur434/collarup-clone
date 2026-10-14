<?php
require __DIR__ . '/../app/bootstrap.php';

echo "Poppler check (project-local)\n";
echo "============================\n\n";

$dir = PopplerTool::binDir();
if ($dir === '') {
    echo "[FAIL] No Poppler in tools/poppler/bin (or POPPLER_BIN_DIR).\n";
    echo "       See tools/poppler/README.md\n";
    exit(1);
}

echo "[OK] Poppler bin: {$dir}\n";

foreach (['pdftotext', 'pdfseparate', 'pdfunite'] as $tool) {
    $path = PopplerTool::executablePath($dir, $tool);
    if ($path === '') {
        echo "[WARN] Missing {$tool}\n";
    } else {
        echo "[OK] {$tool}\n";
    }
}

if (PopplerTool::hasSplitTools()) {
    echo "\n[OK] Compiled PDF split (pdfseparate + pdfunite) ready.\n";
} else {
    echo "\n[WARN] Install pdfseparate + pdfunite for best compiled-PDF bulk import.\n";
}

exit(0);
