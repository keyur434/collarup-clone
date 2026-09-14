<?php



class AnalyzeInterviewJob

{

    public function handle($payload)

    {

        $interviewId = $payload['interview_id'];

        $interview = db()->fetch(

            'SELECT i.*, a.job_id, a.candidate_id FROM interviews i JOIN applications a ON a.id = i.application_id WHERE i.id = ?',

            [$interviewId]

        );

        if (!$interview) {

            throw new RuntimeException('Interview not found');

        }



        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$interview['job_id']]);

        $criteria = db()->fetchAll('SELECT name FROM job_rubric_criteria WHERE job_id = ? AND is_active = 1 ORDER BY sort_order', [$interview['job_id']]);

        $questions = db()->fetchAll('SELECT question_text FROM job_interview_questions WHERE job_id = ? ORDER BY sort_order', [$interview['job_id']]);



        $ffmpeg = new FFmpegService();

        $storage = new OneDriveStorageService();

        $elevenlabs = new ElevenLabsService();

        $openai = new AzureOpenAIService();



        $media = db()->fetch('SELECT TOP 1 * FROM interview_media WHERE interview_id = ? ORDER BY id DESC', [$interviewId]);

        $tempDir = BASE_PATH . '/storage/temp/';

        $videoPath = $tempDir . $interviewId . '_source.mp4';

        $audioPath = $tempDir . $interviewId . '_audio.mp3';



        if (!$media || empty($media['blob_name'])) {

            throw new RuntimeException('No video available for transcription');

        }



        db()->update('interview_media', ['processing_status' => 'transcribing'], 'id = :id', ['id' => $media['id']]);

        $storage->downloadToLocal($media, $videoPath);

        $skills = json_decode($job['key_skills'], true);
        $skillsStr = is_array($skills) ? implode(', ', $skills) : '';
        $keyterms = is_array($skills) ? $skills : [];
        if (!empty($job['title'])) {
            $keyterms[] = $job['title'];
        }

        // ElevenLabs Scribe v2 accepts video directly (same as Python pipeline)
        $sttPath = $videoPath;
        try {
            $transcriptData = $elevenlabs->transcribe($sttPath, ['keyterms' => $keyterms]);
        } catch (RuntimeException $e) {
            $ffmpeg->extractAudio($videoPath, $audioPath);
            $transcriptData = $elevenlabs->transcribe($audioPath, ['keyterms' => $keyterms]);
            $sttPath = $audioPath;
        }

        $rawText = $transcriptData['raw_full_text'] ?? $transcriptData['full_text'];
        $refinedText = $openai->refineTranscript($rawText, [
            'job_title' => $job['title'],
            'key_skills' => $skillsStr,
        ]);
        $transcriptData['full_text'] = $refinedText;

        db()->query('DELETE FROM transcripts WHERE interview_id = ?', [$interviewId]);

        $transcriptRow = [
            'interview_id' => $interviewId,
            'full_text' => $transcriptData['full_text'],
            'segments' => json_encode($transcriptData['segments']),
            'provider' => 'elevenlabs',
        ];
        if ($this->transcriptsHasRawColumn()) {
            $transcriptRow['raw_full_text'] = $rawText;
        }
        db()->insert('transcripts', $transcriptRow);

        db()->update('interview_media', ['processing_status' => 'analyzing'], 'id = :id', ['id' => $media['id']]);

        $questionsStr = '';

        foreach ($questions as $q) {

            $questionsStr .= '- ' . $q['question_text'] . "\n";

        }



        $criteriaNames = array_column($criteria, 'name');

        if (empty($criteriaNames)) {

            $criteriaNames = ['Relevant Experience', 'Cultural Fit', 'Technical Skills'];

        }



        $analysisContext = [
            'job_title' => $job['title'],
            'key_skills' => $skillsStr,
            'qualifications' => $job['qualifications'],
            'must_have' => $job['must_have_criteria'],
            'criteria' => $criteriaNames,
            'questions' => $questionsStr,
            'transcript' => $transcriptData['full_text'],
        ];

        $orchestrator = new InterviewAnalysisOrchestrator($openai);
        $analysis = $orchestrator->analyze($interviewId, $analysisContext);



        if (is_string($analysis)) {

            $analysis = json_decode($analysis, true);

        }



        $reportId = uuid();

        $overall = isset($analysis['overall_score']) ? $analysis['overall_score'] : 0;

        $label = score_label($overall);



        db()->query('DELETE FROM interview_reports WHERE interview_id = ?', [$interviewId]);

        db()->insert('interview_reports', [

            'id' => $reportId,

            'interview_id' => $interviewId,

            'overall_score' => $overall,

            'experience_score' => isset($analysis['experience_score']) ? $analysis['experience_score'] : null,

            'culture_score' => isset($analysis['culture_score']) ? $analysis['culture_score'] : null,

            'soft_skills_score' => isset($analysis['soft_skills_score']) ? $analysis['soft_skills_score'] : null,

            'score_label' => $label['label'],

            'overview_strengths' => isset($analysis['overview']['strengths']) ? $analysis['overview']['strengths'] : '',

            'overview_weaknesses' => isset($analysis['overview']['weaknesses']) ? $analysis['overview']['weaknesses'] : '',

            'overview_red_flags' => isset($analysis['overview']['red_flags']) ? $analysis['overview']['red_flags'] : '',

            'overview_team_fit' => isset($analysis['overview']['team_fit']) ? $analysis['overview']['team_fit'] : '',

            'overview_cheating_detection' => isset($analysis['overview']['cheating_detection']) ? $analysis['overview']['cheating_detection'] : '',

            'overview_qualification_match' => isset($analysis['overview']['qualification_match']) ? $analysis['overview']['qualification_match'] : '',

            'overview_logistics' => isset($analysis['overview']['logistics']) ? $analysis['overview']['logistics'] : '',

            'overview_follow_up' => isset($analysis['overview']['follow_up']) ? $analysis['overview']['follow_up'] : '',

            'overview_recommendation' => isset($analysis['overview']['recommendation']) ? $analysis['overview']['recommendation'] : '',

            'raw_analysis_json' => json_encode($analysis),

        ]);



        if (!empty($analysis['criterion_scores'])) {

            foreach ($analysis['criterion_scores'] as $idx => $cs) {

                db()->insert('interview_criterion_scores', [

                    'report_id' => $reportId,

                    'criterion_name' => $cs['name'],

                    'score' => $cs['score'],

                ]);

            }

        }



        if (!empty($analysis['qa_notes'])) {

            foreach ($analysis['qa_notes'] as $idx => $qa) {

                db()->insert('interview_qa_notes', [

                    'report_id' => $reportId,

                    'question_text' => $qa['question'],

                    'answer_summary' => $qa['answer_summary'],

                    'sort_order' => $idx,

                ]);

            }

        }



        $integrity = isset($analysis['integrity']) ? $analysis['integrity'] : [];

        $events = db()->fetchAll('SELECT * FROM integrity_events WHERE interview_id = ?', [$interviewId]);

        $windowSwitches = 0;

        foreach ($events as $ev) {

            if ($ev['event_type'] === 'window_switch') {

                $windowSwitches++;

            }

        }



        db()->query('DELETE FROM integrity_assessments WHERE interview_id = ?', [$interviewId]);

        db()->insert('integrity_assessments', [

            'interview_id' => $interviewId,

            'cheating_likelihood' => isset($integrity['cheating_likelihood']) ? $integrity['cheating_likelihood'] : 'low',

            'external_display' => empty($events) ? 'na' : 'not_detected',

            'window_switching' => $windowSwitches,

            'content_copied' => empty($events) ? 'na' : 'not_detected',

            'ai_answer_patterns' => isset($integrity['ai_answer_patterns']) ? $integrity['ai_answer_patterns'] : 'na',

        ]);



        db()->update('interview_media', ['processing_status' => 'ready'], 'id = :id', ['id' => $media['id']]);

        db()->update('interviews', [

            'status' => 'completed',

            'completed_at' => date('Y-m-d H:i:s'),

        ], 'id = :id', ['id' => $interviewId]);

        db()->update('applications', ['status' => 'completed'], 'id = :id', ['id' => $interview['application_id']]);

        try {
            $notify = new NotificationService();
            $notify->notifyReportReady($interviewId);
        } catch (Exception $e) {
            // non-fatal
        }

        @unlink($videoPath);
        @unlink($audioPath);
    }

    private function transcriptsHasRawColumn()
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $row = db()->fetch(
                "SELECT COL_LENGTH('transcripts', 'raw_full_text') AS col_len"
            );
            $has = !empty($row['col_len']);
        } catch (Exception $e) {
            $has = false;
        }
        return $has;
    }
}


