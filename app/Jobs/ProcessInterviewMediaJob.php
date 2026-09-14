<?php



class ProcessInterviewMediaJob

{

    public function handle($payload)

    {

        $interviewId = $payload['interview_id'];

        $interview = db()->fetch('SELECT * FROM interviews WHERE id = ?', [$interviewId]);

        if (!$interview) {

            throw new RuntimeException('Interview not found');

        }



        InterviewWorkflowService::markProcessing($interviewId);



        $mediaId = isset($payload['media_id']) ? $payload['media_id'] : null;

        if ($mediaId) {

            $media = db()->fetch('SELECT TOP 1 * FROM interview_media WHERE id = ?', [$mediaId]);

        } else {

            $media = db()->fetch('SELECT TOP 1 * FROM interview_media WHERE interview_id = ? ORDER BY id DESC', [$interviewId]);

        }

        if (!$media) {

            throw new RuntimeException('No media found for interview');

        }



        $storage = new OneDriveStorageService();

        $ffmpeg = new FFmpegService();

        $tempDir = BASE_PATH . '/storage/temp/';

        if (!is_dir($tempDir)) {

            mkdir($tempDir, 0755, true);

        }



        $localSource = null;

        if (!empty($payload['local_path']) && file_exists($payload['local_path'])) {

            $localSource = $payload['local_path'];

        } elseif (!empty($media['blob_container']) && $media['blob_container'] === 'local' && !empty($media['blob_name'])) {

            $localSource = BASE_PATH . '/storage/videos/' . $media['blob_name'];

        }



        if (!$localSource || !file_exists($localSource)) {

            throw new RuntimeException('Source video file not found');

        }



        db()->update('interview_media', ['processing_status' => 'uploading'], 'id = :id', ['id' => $media['id']]);



        $ext = strtolower(pathinfo($localSource, PATHINFO_EXTENSION));

        if (!$ext) {

            $ext = 'mp4';

        }

        $year = date('Y');

        $month = date('m');

        $remotePath = $year . '/' . $month . '/' . $interviewId . '.' . $ext;

        $uploadResult = $storage->upload($localSource, $remotePath);

        $duration = $ffmpeg->getDuration($localSource);



        db()->update('interview_media', [

            'storage_provider' => $uploadResult['storage_provider'],

            'drive_item_id' => $uploadResult['drive_item_id'],

            'drive_path' => $uploadResult['drive_path'],

            'blob_container' => $uploadResult['blob_container'],

            'blob_name' => $uploadResult['blob_name'],

            'file_size_bytes' => filesize($localSource),

            'duration_seconds' => $duration,

            'playback_mode' => 'blob',

            'processing_status' => 'ready',

        ], 'id = :id', ['id' => $media['id']]);



        if (isset($payload['local_path']) && file_exists($payload['local_path'])) {

            @unlink($payload['local_path']);

        }



        $queue = new QueueService();

        $queue->push('AnalyzeInterview', ['interview_id' => $interviewId]);

    }

}


