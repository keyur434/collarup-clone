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

    /** @return string Absolute path to storage/temp (created if missing). */
    public static function tempMediaDir()
    {
        $dir = BASE_PATH . '/storage/temp';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $real = realpath($dir);
        return $real ?: $dir;
    }

    public static function isUnderTempDir($path)
    {
        if (!$path || !is_file($path)) {
            return false;
        }
        $real = realpath($path);
        if (!$real) {
            return false;
        }
        $temp = strtolower(str_replace('\\', '/', self::tempMediaDir()));
        $realNorm = strtolower(str_replace('\\', '/', $real));
        return strpos($realNorm, rtrim($temp, '/') . '/') === 0;
    }

    /** Remove upload / analysis scratch files for one interview (not canonical storage/videos copies). */
    public static function purgeInterviewTempFiles($interviewId)
    {
        $dir = BASE_PATH . '/storage/temp/';
        if (!is_dir($dir)) {
            return 0;
        }
        $prefix = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $interviewId);
        if ($prefix === '') {
            return 0;
        }
        $removed = 0;
        foreach (glob($dir . $prefix . '_*') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    public static function deleteTempFileIfAny($path)
    {
        if (self::isUnderTempDir($path)) {
            return @unlink($path);
        }
        return false;
    }

    /**
     * UI hint for interviews stuck in processing (no report yet).
     */
    public static function processingHint(array $interview)
    {
        $id = $interview['id'];
        $report = db()->fetch('SELECT id FROM interview_reports WHERE interview_id = ?', [$id]);
        if ($report) {
            return ['code' => 'report_ready', 'message' => 'Report exists — refresh the page.'];
        }

        $media = db()->fetch(
            'SELECT TOP 1 blob_name, processing_status FROM interview_media WHERE interview_id = ? ORDER BY id DESC',
            [$id]
        );
        $hasVideo = $media && !empty($media['blob_name']);

        $job = db()->fetch(
            "SELECT TOP 1 job_type, status, error_message FROM job_queue
             WHERE payload LIKE ? AND status IN ('pending', 'processing')
             ORDER BY id DESC",
            ['%' . $id . '%']
        );

        if ($job) {
            $msg = 'Background job ' . $job['job_type'] . ' (' . $job['status'] . ')';
            if (!empty($job['error_message'])) {
                $msg .= ' — ' . mb_substr(trim($job['error_message']), 0, 120);
            }
            return ['code' => 'queued', 'message' => $msg, 'can_retry_analyze' => false, 'can_sync_recording' => false];
        }

        if (!$hasVideo) {
            $rfs = $interview['recording_fetch_state'] ?? '';
            return [
                'code' => 'no_video',
                'message' => 'No recording in system yet. Sync Teams recording or upload video.',
                'can_retry_analyze' => false,
                'can_sync_recording' => true,
            ];
        }

        $failed = db()->fetch(
            "SELECT TOP 1 error_message FROM job_queue
             WHERE payload LIKE ? AND status = 'failed' ORDER BY id DESC",
            ['%' . $id . '%']
        );
        $rfs = $interview['recording_fetch_state'] ?? '';
        $msg = 'Video ready but analysis did not finish.';
        if ($failed && !empty($failed['error_message'])) {
            $msg .= ' Last error: ' . mb_substr(trim($failed['error_message']), 0, 160);
        } else {
            $msg .= ' Queue worker may be stopped — use Retry analysis.';
        }

        return [
            'code' => 'stuck_analyze',
            'message' => $msg,
            'can_retry_analyze' => true,
            'can_sync_recording' => $rfs === 'failed',
        ];
    }

    public static function requeueAnalyze($interviewId)
    {
        $media = db()->fetch(
            'SELECT TOP 1 id, blob_name FROM interview_media WHERE interview_id = ? ORDER BY id DESC',
            [$interviewId]
        );
        if (!$media || empty($media['blob_name'])) {
            throw new RuntimeException('No video on file. Sync Teams recording or upload video first.');
        }

        db()->query(
            "UPDATE job_queue SET status = 'failed', error_message = N'superseded by requeue'
             WHERE status IN ('pending', 'processing') AND job_type = 'AnalyzeInterview'
               AND payload LIKE ?",
            ['%' . $interviewId . '%']
        );

        self::markProcessing($interviewId);
        $queue = new QueueService();
        $queue->push('AnalyzeInterview', ['interview_id' => $interviewId]);
        return true;
    }

    /** Re-queue analysis when processing with video but no active queue job. */
    public static function healStuckProcessingInterviews($limit = 10)
    {
        $rows = db()->fetchAll(
            "SELECT TOP " . (int) $limit . " i.id
             FROM interviews i
             WHERE i.status = 'processing'
               AND NOT EXISTS (SELECT 1 FROM interview_reports ir WHERE ir.interview_id = i.id)
               AND EXISTS (
                   SELECT 1 FROM interview_media im
                   WHERE im.interview_id = i.id AND im.blob_name IS NOT NULL AND LTRIM(RTRIM(im.blob_name)) <> ''
               )
               AND NOT EXISTS (
                   SELECT 1 FROM job_queue jq
                   WHERE jq.status IN ('pending', 'processing')
                     AND jq.payload LIKE '%' + i.id + '%'
               )"
        );
        $n = 0;
        foreach ($rows as $row) {
            try {
                self::requeueAnalyze($row['id']);
                $n++;
            } catch (Exception $e) {
            }
        }
        return $n;
    }
}
