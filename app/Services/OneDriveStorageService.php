<?php



class OneDriveStorageService

{

    private $graph;

    private $driveId;

    private $folderPath;



    public function __construct()

    {

        $config = config('services')['onedrive'];

        $this->graph = new MicrosoftGraphService('sync');

        $this->driveId = $config['drive_id'];

        $this->folderPath = rtrim($config['folder_path'], '/');

    }



    public function isConfigured()

    {

        return $this->graph->isConfigured() && !empty($this->driveId);

    }



    public function upload($localPath, $remoteRelativePath)

    {

        $remoteRelativePath = ltrim(str_replace('\\', '/', $remoteRelativePath), '/');

        $fullPath = $this->folderPath . '/' . $remoteRelativePath;



        if (!$this->isConfigured()) {

            return $this->uploadLocal($localPath, $remoteRelativePath);

        }



        $this->ensureFolder(dirname($fullPath));



        $content = file_get_contents($localPath);

        $encodedPath = $this->encodePath($fullPath);

        $url = '/drives/' . $this->driveId . '/root:/' . $encodedPath . ':/content';



        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

        $mime = $ext === 'webm' ? 'video/webm' : 'video/mp4';

        $item = $this->graph->uploadBinary($url, $content, $mime);



        return [

            'storage_provider' => 'onedrive',

            'drive_item_id' => isset($item['id']) ? $item['id'] : null,

            'drive_path' => $fullPath,

            'blob_name' => $remoteRelativePath,

            'blob_container' => 'onedrive',

        ];

    }



    public function downloadToLocal($media, $localPath)

    {

        if (!empty($media['storage_provider']) && $media['storage_provider'] === 'local') {

            $src = BASE_PATH . '/storage/videos/' . $media['blob_name'];

            if (!file_exists($src)) {

                throw new RuntimeException('Local video not found');

            }

            copy($src, $localPath);

            return;

        }



        if (!$this->isConfigured()) {

            $src = BASE_PATH . '/storage/videos/' . $media['blob_name'];

            if (!file_exists($src)) {

                throw new RuntimeException('Local video not found');

            }

            copy($src, $localPath);

            return;

        }



        if (!empty($media['drive_item_id'])) {

            $meta = $this->graph->request('GET', '/drives/' . $this->driveId . '/items/' . $media['drive_item_id']);

        } else {

            $path = !empty($media['drive_path']) ? $media['drive_path'] : ($this->folderPath . '/' . $media['blob_name']);

            $meta = $this->graph->request('GET', '/drives/' . $this->driveId . '/root:/' . $this->encodePath($path));

        }



        if (empty($meta['@microsoft.graph.downloadUrl'])) {

            throw new RuntimeException('No download URL for OneDrive item');

        }



        $this->graph->download($meta['@microsoft.graph.downloadUrl'], $localPath);

    }



    public function getViewUrl($media, $expiryMinutes = 60)

    {

        if (!empty($media['storage_provider']) && $media['storage_provider'] === 'local') {

            return url('storage/video.php?f=' . urlencode($media['blob_name']));

        }



        if (!$this->isConfigured()) {

            return url('storage/video.php?f=' . urlencode($media['blob_name']));

        }



        $itemId = !empty($media['drive_item_id']) ? $media['drive_item_id'] : null;

        if (!$itemId) {

            $path = !empty($media['drive_path']) ? $media['drive_path'] : ($this->folderPath . '/' . $media['blob_name']);

            $meta = $this->graph->request('GET', '/drives/' . $this->driveId . '/root:/' . $this->encodePath($path));

            $itemId = $meta['id'];

        }



        $link = $this->graph->request('POST', '/drives/' . $this->driveId . '/items/' . $itemId . '/createLink', [

            'type' => 'view',

            'scope' => 'organization',

        ]);



        if (!empty($link['link']['webUrl'])) {

            return $link['link']['webUrl'];

        }



        $meta = $this->graph->request('GET', '/drives/' . $this->driveId . '/items/' . $itemId);

        if (!empty($meta['@microsoft.graph.downloadUrl'])) {

            return $meta['@microsoft.graph.downloadUrl'];

        }



        throw new RuntimeException('Could not create playback link');

    }



    public function listNewRecordings($sinceIso = null)

    {

        if (!$this->isConfigured()) {

            return [];

        }



        $path = $this->encodePath($this->folderPath);

        $children = $this->graph->request('GET', '/drives/' . $this->driveId . '/root:/' . $path . ':/children?$orderby=createdDateTime desc&$top=50');

        $items = isset($children['value']) ? $children['value'] : [];

        $videos = [];



        foreach ($items as $item) {

            if (empty($item['file'])) {

                continue;

            }

            $name = isset($item['name']) ? $item['name'] : '';

            if (!preg_match('/\.(mp4|webm|mov|mkv)$/i', $name)) {

                continue;

            }

            if ($sinceIso && !empty($item['createdDateTime']) && $item['createdDateTime'] < $sinceIso) {

                continue;

            }

            $videos[] = $item;

        }



        return $videos;

    }



    public function copyItemToFolder($sourceDriveItemId, $destFileName)

    {

        $destPath = $this->folderPath . '/' . ltrim($destFileName, '/');

        $this->ensureFolder(dirname($destPath));



        $item = $this->graph->request('GET', '/drives/' . $this->driveId . '/items/' . $sourceDriveItemId);

        $downloadUrl = isset($item['@microsoft.graph.downloadUrl']) ? $item['@microsoft.graph.downloadUrl'] : null;

        if (!$downloadUrl) {

            throw new RuntimeException('Cannot read source recording item');

        }



        $temp = BASE_PATH . '/storage/temp/' . uuid() . '_' . basename($destFileName);

        $this->graph->download($downloadUrl, $temp);

        $result = $this->upload($temp, $destFileName);

        @unlink($temp);

        return $result;

    }



    private function uploadLocal($localPath, $remoteRelativePath)

    {

        $dest = BASE_PATH . '/storage/videos/' . $remoteRelativePath;

        $dir = dirname($dest);

        if (!is_dir($dir)) {

            mkdir($dir, 0755, true);

        }

        copy($localPath, $dest);



        return [

            'storage_provider' => 'local',

            'drive_item_id' => null,

            'drive_path' => null,

            'blob_name' => $remoteRelativePath,

            'blob_container' => 'local',

        ];

    }



    private function ensureFolder($folderPath)

    {

        if ($folderPath === '.' || $folderPath === '/' || $folderPath === $this->folderPath) {

            return;

        }

        try {

            $this->graph->request('GET', '/drives/' . $this->driveId . '/root:/' . $this->encodePath($folderPath));

        } catch (Exception $e) {

            $parent = dirname($folderPath);

            if ($parent !== $folderPath) {

                $this->ensureFolder($parent);

            }

            $name = basename($folderPath);

            $parentPath = $parent === '.' ? $this->folderPath : $parent;

            $this->graph->request('POST', '/drives/' . $this->driveId . '/root:/' . $this->encodePath($parentPath) . ':/children', [

                'name' => $name,

                'folder' => new stdClass(),

                '@microsoft.graph.conflictBehavior' => 'fail',

            ]);

        }

    }



    private function encodePath($path)

    {

        $parts = explode('/', trim(str_replace('\\', '/', $path), '/'));

        return implode('/', array_map('rawurlencode', $parts));

    }

}


