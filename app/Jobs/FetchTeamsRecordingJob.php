<?php



class FetchTeamsRecordingJob

{

    public function handle($payload)

    {

        $interviewId = $payload['interview_id'];

        $mediaId = isset($payload['media_id']) ? $payload['media_id'] : null;

        $attempt = isset($payload['attempt']) ? (int) $payload['attempt'] : 1;



        $interview = db()->fetch('SELECT * FROM interviews WHERE id = ?', [$interviewId]);

        if (!$interview || empty($interview['meeting_url'])) {

            throw new RuntimeException('Interview or meeting URL not found');

        }



        InterviewWorkflowService::markProcessing($interviewId);



        $teams = new TeamsRecordingService();

        $teams->fetchForInterview($interviewId, $interview['meeting_url'], $mediaId);



        $queue = new QueueService();

        $queue->push('AnalyzeInterview', ['interview_id' => $interviewId]);

    }

}


