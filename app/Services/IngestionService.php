<?php



class IngestionService

{

    public static function syncOneDriveFolder()

    {

        $storage = new OneDriveStorageService();

        if (!$storage->isConfigured()) {

            return 0;

        }



        $watermark = db()->fetch(

            "SELECT TOP 1 * FROM ingestion_watermarks WHERE source_key = 'onedrive_recordings'"

        );

        $since = $watermark && $watermark['last_synced_at']

            ? date('c', strtotime($watermark['last_synced_at']))

            : null;



        $items = $storage->listNewRecordings($since);

        $imported = 0;



        foreach ($items as $item) {

            $driveItemId = $item['id'];

            $exists = db()->fetch(

                'SELECT TOP 1 id FROM pending_recordings WHERE drive_item_id = ?',

                [$driveItemId]

            );

            if ($exists) {

                continue;

            }



            $existsMedia = db()->fetch(

                'SELECT TOP 1 id FROM interview_media WHERE drive_item_id = ?',

                [$driveItemId]

            );

            if ($existsMedia) {

                continue;

            }



            db()->insert('pending_recordings', [

                'id' => uuid(),

                'drive_item_id' => $driveItemId,

                'drive_path' => isset($item['parentReference']['path']) ? $item['parentReference']['path'] . '/' . $item['name'] : null,

                'original_filename' => isset($item['name']) ? $item['name'] : null,

                'file_size_bytes' => isset($item['size']) ? $item['size'] : null,

                'recorded_at' => isset($item['createdDateTime']) ? date('Y-m-d H:i:s', strtotime($item['createdDateTime'])) : null,

                'status' => 'pending_assignment',

            ]);

            $imported++;

        }



        db()->update('ingestion_watermarks', [

            'last_synced_at' => date('Y-m-d H:i:s'),

        ], "source_key = :source_key", ['source_key' => 'onedrive_recordings']);



        return $imported;

    }

}


