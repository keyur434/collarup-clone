<?php



class TeamsRecordingService

{

    private $graph;

    private $storage;

    private $organizerUserId;



    public function __construct()

    {

        $config = config('services')['onedrive'];

        $this->graph = new MicrosoftGraphService('sync');

        $this->storage = new OneDriveStorageService();

        $this->organizerUserId = $config['teams_organizer_user_id'];

    }



    public function isConfigured()

    {

        return $this->graph->isConfigured() && !empty($this->organizerUserId);

    }



    public function fetchForInterview($interviewId, $meetingUrl, $mediaId = null)

    {

        if (!$this->isConfigured()) {

            throw new RuntimeException('Teams recording fetch is not configured (Graph sync app + TEAMS_ORGANIZER_USER_ID).');

        }



        $userId = $this->resolveUserId($this->organizerUserId);

        $meeting = $this->findMeetingByJoinUrl($userId, $meetingUrl);

        if (!$meeting) {

            throw new RuntimeException('Teams meeting not found for URL. Recording may not be ready yet.');

        }



        $recordings = $this->graph->request(

            'GET',

            '/users/' . rawurlencode($userId) . '/onlineMeetings/' . $meeting['id'] . '/recordings'

        );

        $items = isset($recordings['value']) ? $recordings['value'] : [];

        if (empty($items)) {

            throw new RuntimeException('No Teams recording available yet.');

        }



        $recording = $items[count($items) - 1];

        $tempDir = BASE_PATH . '/storage/temp/';

        if (!is_dir($tempDir)) {

            mkdir($tempDir, 0755, true);

        }

        $tempFile = $tempDir . $interviewId . '_teams.mp4';



        if (!empty($recording['recordingContentUrl'])) {

            $this->graph->download($recording['recordingContentUrl'], $tempFile);

        } elseif (!empty($recording['id'])) {

            $content = $this->graph->request(

                'GET',

                '/users/' . rawurlencode($userId) . '/onlineMeetings/' . $meeting['id'] . '/recordings/' . $recording['id'] . '/content'

            );

            if (is_string($content)) {

                file_put_contents($tempFile, $content);

            } else {

                throw new RuntimeException('Unexpected recording content response.');

            }

        } else {

            throw new RuntimeException('Recording has no downloadable content.');

        }



        $year = date('Y');

        $month = date('m');

        $remotePath = $year . '/' . $month . '/' . $interviewId . '.mp4';

        $upload = $this->storage->upload($tempFile, $remotePath);

        @unlink($tempFile);



        $ffmpeg = new FFmpegService();

        $duration = $ffmpeg->getDuration($tempFile);

        if (!$duration && file_exists($tempFile)) {

            $duration = $ffmpeg->getDuration(BASE_PATH . '/storage/temp/' . $interviewId . '_teams.mp4');

        }



        $update = [

            'storage_provider' => $upload['storage_provider'],

            'drive_item_id' => $upload['drive_item_id'],

            'drive_path' => $upload['drive_path'],

            'blob_container' => $upload['blob_container'],

            'blob_name' => $upload['blob_name'],

            'file_size_bytes' => filesize($tempFile) ?: null,

            'duration_seconds' => $duration,

            'playback_mode' => 'onedrive',

            'processing_status' => 'ingested',

            'external_url' => $meetingUrl,

        ];



        if ($mediaId) {

            db()->update('interview_media', $update, 'id = :id', ['id' => $mediaId]);

        } else {

            db()->insert('interview_media', array_merge($update, [

                'interview_id' => $interviewId,

                'media_type' => 'teams',

                'original_filename' => $interviewId . '.mp4',

            ]));

        }



        return $upload;

    }



    private function resolveUserId($identifier)

    {

        if (strpos($identifier, '@') !== false) {

            $user = $this->graph->request('GET', '/users/' . rawurlencode($identifier));

            return $user['id'];

        }

        return $identifier;

    }



    private function findMeetingByJoinUrl($userId, $joinUrl)

    {

        $filter = rawurlencode("JoinWebUrl eq '" . str_replace("'", "''", $joinUrl) . "'");

        $result = $this->graph->request(

            'GET',

            '/users/' . rawurlencode($userId) . '/onlineMeetings?$filter=' . $filter

        );

        $meetings = isset($result['value']) ? $result['value'] : [];

        return !empty($meetings) ? $meetings[0] : null;

    }

}


