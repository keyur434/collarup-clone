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
            $teams->deferRecordingFetch($interviewId, 30);
            db()->update('interviews', ['recording_fetch_state' => 'waiting'], 'id = :id', ['id' => $interviewId]);
            return;
        }
    }
}
