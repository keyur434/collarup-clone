<?php

class InterviewWorkflowService
{
    public static function interviewContext($interviewId)
    {
        return db()->fetch(
            'SELECT i.*, a.job_id, a.candidate_id, a.id as application_id
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             WHERE i.id = ?',
            [$interviewId]
        );
    }

    public static function redirectUrl($interviewId)
    {
        $ctx = self::interviewContext($interviewId);
        if (!$ctx) {
            return url('candidates');
        }
        return url('jobs/' . $ctx['job_id'] . '/interviews');
    }

    public static function markProcessing($interviewId)
    {
        $ctx = self::interviewContext($interviewId);
        if (!$ctx) {
            return;
        }
        db()->update('interviews', ['status' => 'processing'], 'id = :id', ['id' => $interviewId]);
        db()->update('applications', ['status' => 'processing'], 'id = :id', ['id' => $ctx['application_id']]);
    }

    public static function markFailed($interviewId, $message = '')
    {
        $ctx = self::interviewContext($interviewId);
        if (!$ctx) {
            return;
        }
        db()->update('interviews', ['status' => 'pending'], 'id = :id', ['id' => $interviewId]);
        db()->update('applications', ['status' => 'pending'], 'id = :id', ['id' => $ctx['application_id']]);
    }

    public static function queueMediaProcessing($interviewId, $localPath, $mediaId = null)
    {
        self::markProcessing($interviewId);
        $queue = new QueueService();
        $queue->push('ProcessInterviewMedia', [
            'interview_id' => $interviewId,
            'local_path' => $localPath,
            'media_id' => $mediaId,
        ]);
    }

    public static function isDirectVideoUrl($url)
    {
        return (bool) preg_match('/\.(mp4|webm|mov|mkv)(\?.*)?$/i', $url);
    }

    public static function downloadRemoteVideo($url, $destPath)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($data === false || $code < 200 || $code >= 300) {
            throw new RuntimeException('Could not download video from URL: ' . ($err ?: 'HTTP ' . $code));
        }

        if (file_put_contents($destPath, $data) === false) {
            throw new RuntimeException('Could not save downloaded video.');
        }

        return $destPath;
    }
}
