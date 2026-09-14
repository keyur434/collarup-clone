<?php

class QueueService
{
    public function push($jobType, $payload, $queueName = 'default')
    {
        db()->insert('job_queue', [
            'queue_name' => $queueName,
            'job_type' => $jobType,
            'payload' => json_encode($payload),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }

    public function processNext($queueName = 'default')
    {
        $job = db()->fetch(
            "SELECT TOP 1 * FROM job_queue WHERE queue_name = ? AND status = 'pending' AND available_at <= SYSUTCDATETIME() ORDER BY id ASC",
            [$queueName]
        );
        if (!$job) {
            return false;
        }

        db()->update('job_queue', [
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s'),
            'attempts' => $job['attempts'] + 1,
        ], 'id = :id', ['id' => $job['id']]);

        try {
            $payload = json_decode($job['payload'], true);
            $this->dispatch($job['job_type'], $payload);
            db()->update('job_queue', [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $job['id']]);
        } catch (Exception $e) {
            $attempts = $job['attempts'] + 1;
            $status = $attempts >= $job['max_attempts'] ? 'failed' : 'pending';
            db()->update('job_queue', [
                'status' => $status,
                'error_message' => $e->getMessage(),
                'available_at' => date('Y-m-d H:i:s', time() + 60),
            ], 'id = :id', ['id' => $job['id']]);

            $payload = json_decode($job['payload'], true);
            if ($status === 'failed' && !empty($payload['interview_id'])) {
                InterviewWorkflowService::markFailed($payload['interview_id'], $e->getMessage());
            }
            return false;
        }

        return true;
    }

    private function dispatch($jobType, $payload)
    {
        switch ($jobType) {
            case 'ProcessInterviewMedia':
                $job = new ProcessInterviewMediaJob();
                $job->handle($payload);
                break;
            case 'AnalyzeInterview':
                $job = new AnalyzeInterviewJob();
                $job->handle($payload);
                break;
            case 'FetchTeamsRecording':
                $job = new FetchTeamsRecordingJob();
                $job->handle($payload);
                break;
            default:
                throw new RuntimeException('Unknown job type: ' . $jobType);
        }
    }

    public function processAll($limit = 10)
    {
        $processed = 0;
        while ($processed < $limit && $this->processNext()) {
            $processed++;
        }
        return $processed;
    }
}
