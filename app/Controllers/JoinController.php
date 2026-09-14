<?php

class JoinController
{
    public function show($interviewId)
    {
        $row = db()->fetch(
            'SELECT i.id, i.scheduled_date, i.scheduled_start, i.meeting_url, i.status,
                    c.first_name, j.title as job_title
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             WHERE i.id = ?',
            [$interviewId]
        );
        if (!$row) {
            http_response_code(404);
            die('Interview not found');
        }

        echo view('interviews.join', [
            'title' => 'Interview Session',
            'interview' => $row,
            'integrityApi' => url('api/interviews/' . $interviewId . '/integrity-event'),
        ]);
        return;
    }
}
