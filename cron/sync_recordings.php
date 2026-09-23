<?php

require __DIR__ . '/../app/bootstrap.php';



echo '[' . date('Y-m-d H:i:s') . "] sync_recordings start\n";



try {

    $imported = IngestionService::syncOneDriveFolder();

    echo "Imported $imported new recording(s) into pending queue.\n";

} catch (Exception $e) {

    echo 'Error: ' . $e->getMessage() . "\n";

    exit(1);

}



echo "Done.\n";


