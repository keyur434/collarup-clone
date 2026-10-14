<?php

class FetchTeamsRecordingJob
{
    public function handle($payload)
    {
        $interviewId = $payload['interview_id'];
        $mediaId = isset($payload['media_id']) ? $payload['media_id'] : null;

        $interview = db()->fetch('SELECT * FROM interviews WHERE id = ?', [$interviewId]);
        if (!$interview) {
            throw new RuntimeException('Interview or meeting URL not found');
        }

        $teams = new TeamsRecordingService();

        try {
            $teams->fetchForInterview($interviewId, $interview['meeting_url'] ?? null, $mediaId);
            InterviewWorkflowService::markProcessing($interviewId);

            $queue = new QueueService();
            $queue->push('AnalyzeInterview', ['interview_id' => $interviewId]);
        } catch (RecordingNotReadyException $e) {
            $when = $teams->deferRecordingFetchRescheduleUtc($interviewId, 30);
            throw new QueueRescheduleException($e->getMessage(), $when);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $retryable = stripos($msg, 'IO error') !== false
                || stripos($msg, 'not found') !== false
                || stripos($msg, 'download failed') !== false
                || stripos($msg, 'not ready') !== false;
            if ($retryable) {
                $when = $teams->deferRecordingFetchRescheduleUtc($interviewId, 45);
                throw new QueueRescheduleException($msg, $when);
            }
            db()->update('interviews', ['recording_fetch_state' => 'waiting'], 'id = :id', ['id' => $interviewId]);
            throw $e;
        }
    }
}
