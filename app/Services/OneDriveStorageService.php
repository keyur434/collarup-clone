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

        $this->driveId = trim((string) $config['drive_id'], " \t\n\r\0\x0B\"'");
        $this->folderPath = trim(str_replace('\\', '/', $config['folder_path']), '/');
        if ($this->folderPath === '') {
            $this->folderPath = 'InterviewRecordings';
        }

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

        $size = filesize($localPath);
        if ($size === false) {
            throw new RuntimeException('Could not read upload file size.');
        }

        $encodedPath = $this->encodePath($fullPath);
        $item = null;
        if ($size > 4 * 1024 * 1024) {
            $item = $this->uploadLargeFile($localPath, $encodedPath, $size);
        } else {
            $content = file_get_contents($localPath);
            $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
            $mime = $ext === 'webm' ? 'video/webm' : 'video/mp4';
            $url = '/drives/' . $this->driveId . '/root:/' . $encodedPath . ':/content';
            try {
                $item = $this->graph->uploadBinary($url, $content, $mime);
            } catch (RuntimeException $e) {
                if (stripos($e->getMessage(), 'IO error') !== false || stripos($e->getMessage(), 'payload') !== false) {
                    $item = $this->uploadLargeFile($localPath, $encodedPath, $size);
                } else {
                    throw $e;
                }
            }
        }

        return [

            'storage_provider' => 'onedrive',

            'drive_item_id' => isset($item['id']) ? $item['id'] : null,

            'drive_path' => $fullPath,

            'blob_name' => $remoteRelativePath,

            'blob_container' => 'onedrive',

        ];

    }

    /**
     * Graph simple PUT fails on large Teams MP4s — use upload session (chunked).
     *
     * @return array<string, mixed>
     */
    private function uploadLargeFile($localPath, $encodedPath, $size)
    {
        $session = $this->graph->request(
            'POST',
            '/drives/' . $this->driveId . '/root:/' . $encodedPath . ':/createUploadSession',
            ['item' => ['@microsoft.graph.conflictBehavior' => 'replace']]
        );
        if (empty($session['uploadUrl'])) {
            throw new RuntimeException('OneDrive createUploadSession did not return uploadUrl.');
        }

        $uploadUrl = $session['uploadUrl'];
        $chunkSize = 10 * 1024 * 1024;
        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open file for chunked upload.');
        }

        $offset = 0;
        $lastResponse = null;
        while ($offset < $size) {
            $len = (int) min($chunkSize, $size - $offset);
            $data = fread($handle, $len);
            if ($data === false || strlen($data) !== $len) {
                fclose($handle);
                throw new RuntimeException('Chunk read failed during OneDrive upload.');
            }
            $end = $offset + $len - 1;
            $ch = curl_init($uploadUrl);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3600,
                CURLOPT_HTTPHEADER => [
                    'Content-Length: ' . $len,
                    'Content-Range: bytes ' . $offset . '-' . $end . '/' . $size,
                ],
            ]);
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                fclose($handle);
                throw new RuntimeException('OneDrive chunk upload failed: ' . $err);
            }
            if ($code < 200 || $code >= 300) {
                fclose($handle);
                throw new RuntimeException('OneDrive chunk upload HTTP ' . $code . ': ' . substr($response, 0, 200));
            }
            $lastResponse = json_decode($response, true);
            $offset += $len;
        }
        fclose($handle);

        if (is_array($lastResponse) && !empty($lastResponse['id'])) {
            return $lastResponse;
        }
        if (is_array($lastResponse) && !empty($lastResponse['resourceId'])) {
            return ['id' => $lastResponse['resourceId']];
        }
        throw new RuntimeException('OneDrive upload session finished without drive item id.');
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
        $meta = $this->resolveItemMeta($media);
        if (!empty($meta['@microsoft.graph.downloadUrl'])) {
            return $meta['@microsoft.graph.downloadUrl'];
        }
        return $this->getShareWebUrl($media);
    }

    /** SharePoint/OneDrive page — for "open in browser", not HTML5 &lt;video&gt;. */
    public function getShareWebUrl($media)
    {
        if (!empty($media['storage_provider']) && $media['storage_provider'] === 'local') {
            return url('storage/video.php?f=' . urlencode($media['blob_name']));
        }
        if (!$this->isConfigured()) {
            throw new RuntimeException('OneDrive not configured');
        }
        $itemId = $this->resolveItemId($media);
        $link = $this->graph->request('POST', '/drives/' . $this->driveId . '/items/' . $itemId . '/createLink', [
            'type' => 'view',
            'scope' => 'organization',
        ]);
        if (!empty($link['link']['webUrl'])) {
            return $link['link']['webUrl'];
        }
        throw new RuntimeException('Could not create sharing link');
    }

    /**
     * Stream interview video through the app (Graph → browser). Supports Range for seeking.
     */
    public function streamToBrowser($media)
    {
        if (!empty($media['storage_provider']) && $media['storage_provider'] === 'local') {
            $path = BASE_PATH . '/storage/videos/' . $media['blob_name'];
            if (!is_file($path)) {
                http_response_code(404);
                return;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $types = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];
            header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
            header('Content-Length: ' . filesize($path));
            header('Accept-Ranges: bytes');
            readfile($path);
            return;
        }
        if (!$this->isConfigured()) {
            http_response_code(404);
            return;
        }
        $meta = $this->resolveItemMeta($media);
        $downloadUrl = !empty($meta['@microsoft.graph.downloadUrl']) ? $meta['@microsoft.graph.downloadUrl'] : null;
        if ($downloadUrl === null) {
            http_response_code(404);
            return;
        }
        $range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : null;
        $headers = [];
        if ($range) {
            $headers[] = 'Range: ' . $range;
        }
        $ch = curl_init($downloadUrl);
        $statusCode = 200;
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$statusCode) {
                $len = strlen($headerLine);
                $trim = trim($headerLine);
                if (stripos($trim, 'HTTP/') === 0) {
                    if (preg_match('/\s(\d{3})\s/', $trim, $m)) {
                        $statusCode = (int) $m[1];
                    }
                    return $len;
                }
                if (stripos($trim, 'Content-Type:') === 0
                    || stripos($trim, 'Content-Length:') === 0
                    || stripos($trim, 'Content-Range:') === 0
                    || stripos($trim, 'Accept-Ranges:') === 0) {
                    header($trim, stripos($trim, 'Content-Range:') === 0 && $statusCode === 206);
                }
                return $len;
            },
            CURLOPT_WRITEFUNCTION => function ($ch, $data) {
                echo $data;
                return strlen($data);
            },
        ]);
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '') {
            if (!headers_sent()) {
                http_response_code(502);
            }
        } elseif (!headers_sent()) {
            http_response_code($statusCode > 0 ? $statusCode : 200);
        }
    }

    private function resolveItemId($media)
    {
        if (!empty($media['drive_item_id'])) {
            return $media['drive_item_id'];
        }
        $meta = $this->resolveItemMeta($media);
        return $meta['id'];
    }

    private function resolveItemMeta($media)
    {
        if (!empty($media['drive_item_id'])) {
            return $this->graph->request('GET', '/drives/' . $this->driveId . '/items/' . $media['drive_item_id']);
        }
        $path = !empty($media['drive_path']) ? $media['drive_path'] : ($this->folderPath . '/' . $media['blob_name']);
        return $this->graph->request('GET', '/drives/' . $this->driveId . '/root:/' . $this->encodePath($path));
    }



    public function listNewRecordings($sinceIso = null)

    {

        if (!$this->isConfigured()) {

            return [];

        }



        $path = $this->encodePath($this->folderPath);
        // OneDrive business/personal drives reject $orderby=createdDateTime on /children ("Operation not supported").
        $items = $this->listFolderChildren($path, 200);

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

        usort($videos, function ($a, $b) {
            $ta = isset($a['createdDateTime']) ? $a['createdDateTime'] : '';
            $tb = isset($b['createdDateTime']) ? $b['createdDateTime'] : '';
            return strcmp($tb, $ta);
        });

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

        InterviewWorkflowService::deleteTempFileIfAny($localPath);

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

    /**
     * List all items in a folder (handles @odata.nextLink).
     */
    private function listFolderChildren($encodedPath, $pageSize = 100)
    {
        $items = [];
        $next = '/drives/' . $this->driveId . '/root:/' . $encodedPath . ':/children?$top=' . (int) $pageSize;

        while ($next !== null) {
            $children = $this->graph->request('GET', $next);
            if (!empty($children['value']) && is_array($children['value'])) {
                $items = array_merge($items, $children['value']);
            }
            $next = null;
            if (!empty($children['@odata.nextLink'])) {
                $link = $children['@odata.nextLink'];
                if (strpos($link, 'https://graph.microsoft.com/v1.0') === 0) {
                    $next = substr($link, strlen('https://graph.microsoft.com/v1.0'));
                } else {
                    $next = $link;
                }
            }
        }

        return $items;
    }

}


