<?php



class ApiController

{

    public function departments()

    {

        json_response(db()->fetchAll('SELECT id, name FROM departments ORDER BY name'));

    }



    public function states()

    {

        $countryId = isset($_GET['country_id']) ? $_GET['country_id'] : 0;

        json_response(db()->fetchAll('SELECT id, name FROM states WHERE country_id = ? ORDER BY name', [$countryId]));

    }



    public function cities()

    {

        $stateId = isset($_GET['state_id']) ? $_GET['state_id'] : 0;

        json_response(db()->fetchAll('SELECT id, name FROM cities WHERE state_id = ? ORDER BY name', [$stateId]));

    }



    public function jobStages($id)

    {

        json_response(db()->fetchAll('SELECT id, name FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$id]));

    }



    public function videoPlayback($interviewId)

    {

        $interview = db()->fetch(

            'SELECT i.*, a.job_id FROM interviews i JOIN applications a ON a.id = i.application_id WHERE i.id = ?',

            [$interviewId]

        );

        if (!$interview) {

            json_response(['error' => 'Interview not found'], 404);

        }



        PermissionService::requireJobAccess($interview['job_id']);



        $media = db()->fetch('SELECT TOP 1 * FROM interview_media WHERE interview_id = ? ORDER BY id DESC', [$interviewId]);

        if (!$media || empty($media['blob_name']) || $media['playback_mode'] !== 'blob') {

            json_response(['error' => 'No video'], 404);

        }



        $storage = new OneDriveStorageService();

        $url = $storage->getViewUrl($media);



        ActivityLogService::log('playback', 'interview', $interviewId, [

            'media_id' => $media['id'],

            'storage_provider' => $media['storage_provider'],

        ]);



        json_response(['url' => $url, 'expires_in' => 3600]);

    }



    /** @deprecated Use videoPlayback */

    public function videoSas($interviewId)
    {
        $this->videoPlayback($interviewId);
    }

    public function integrityEvent($interviewId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed'], 405);
        }

        $interview = db()->fetch('SELECT id FROM interviews WHERE id = ?', [$interviewId]);
        if (!$interview) {
            json_response(['error' => 'Interview not found'], 404);
        }

        $eventType = isset($_POST['event_type']) ? trim($_POST['event_type']) : 'unknown';
        $meta = isset($_POST['meta_json']) ? $_POST['meta_json'] : null;

        db()->insert('integrity_events', [
            'interview_id' => $interviewId,
            'event_type' => substr($eventType, 0, 50),
            'event_data' => json_encode([
                'meta' => $meta ? json_decode($meta, true) : null,
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            ]),
        ]);

        json_response(['ok' => true]);
    }
}


