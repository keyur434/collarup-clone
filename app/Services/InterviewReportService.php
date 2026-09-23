<?php

/**
 * Shared interview analysis + report persistence (video pipeline and manual transcript).
 */
class InterviewReportService
{
    public function buildAnalysisContext($jobId, $transcriptText)
    {
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        if (!$job) {
            throw new RuntimeException('Job not found: ' . $jobId);
        }

        $criteria = db()->fetchAll(
            'SELECT name, description FROM job_rubric_criteria WHERE job_id = ? AND is_active = 1 ORDER BY sort_order',
            [$jobId]
        );
        $questions = db()->fetchAll(
            'SELECT question_text FROM job_interview_questions WHERE job_id = ? ORDER BY sort_order',
            [$jobId]
        );

        $questionsStr = '';
        foreach ($questions as $q) {
            $questionsStr .= '- ' . $q['question_text'] . "\n";
        }

        $criteriaNames = array_column($criteria, 'name');
        $criteriaRubric = [];
        foreach ($criteria as $row) {
            $criteriaRubric[] = [
                'name' => $row['name'],
                'description' => isset($row['description']) ? $row['description'] : '',
            ];
        }

        if (empty($criteriaNames)) {
            $criteriaNames = ['Relevant Experience', 'Cultural Fit', 'Technical Skills'];
            $criteriaRubric = [
                ['name' => 'Relevant Experience', 'description' => ''],
                ['name' => 'Cultural Fit', 'description' => ''],
                ['name' => 'Technical Skills', 'description' => ''],
            ];
        }

        $skills = json_decode($job['key_skills'], true);
        $skillsStr = is_array($skills) ? implode(', ', $skills) : '';

        return [
            'job' => $job,
            'context' => [
                'job_title' => $job['title'],
                'key_skills' => $skillsStr,
                'qualifications' => $job['qualifications'],
                'must_have' => $job['must_have_criteria'],
                'criteria' => $criteriaNames,
                'criteria_rubric' => $criteriaRubric,
                'questions' => $questionsStr,
                'transcript' => $transcriptText,
            ],
        ];
    }

    /**
     * Run v3 analysis from transcript text and save report (no video/STT).
     *
     * @param string $interviewId
     * @param string $transcriptText
     * @param array $options skip_refine (bool), provider (string)
     * @return array report_id, report_url, analysis
     */
    public function analyzeFromTranscript($interviewId, $transcriptText, array $options = [])
    {
        $interview = db()->fetch(
            'SELECT i.*, a.job_id, a.candidate_id FROM interviews i JOIN applications a ON a.id = i.application_id WHERE i.id = ?',
            [$interviewId]
        );
        if (!$interview) {
            throw new RuntimeException('Interview not found: ' . $interviewId);
        }

        $transcriptText = trim($transcriptText);
        if ($transcriptText === '') {
            throw new RuntimeException('Transcript is empty');
        }

        $built = $this->buildAnalysisContext($interview['job_id'], $transcriptText);
        $openai = new AzureOpenAIService();

        $skipRefine = !empty($options['skip_refine']);
        $refinedText = $transcriptText;
        if (!$skipRefine) {
            $refinedText = $openai->refineTranscript($transcriptText, [
                'job_title' => $built['job']['title'],
                'key_skills' => $built['context']['key_skills'],
            ]);
            $built['context']['transcript'] = $refinedText;
        }

        $orchestrator = new InterviewAnalysisOrchestrator($openai);
        $analysis = $orchestrator->analyze($interviewId, $built['context']);
        if (is_string($analysis)) {
            $analysis = json_decode($analysis, true);
        }

        $provider = isset($options['provider']) ? $options['provider'] : 'manual';
        $this->saveTranscript($interviewId, $refinedText, $transcriptText, $provider);

        $reportId = $this->persistReport($interviewId, $analysis, $interview['application_id']);

        return [
            'report_id' => $reportId,
            'report_url' => url('jobs/' . $interview['job_id'] . '/reports/' . $reportId),
            'job_id' => $interview['job_id'],
            'analysis' => $analysis,
        ];
    }

    public function saveTranscript($interviewId, $fullText, $rawText = null, $provider = 'manual')
    {
        db()->query('DELETE FROM transcripts WHERE interview_id = ?', [$interviewId]);
        $row = [
            'interview_id' => $interviewId,
            'full_text' => $fullText,
            'segments' => json_encode([]),
            'provider' => $provider,
        ];
        if ($this->transcriptsHasRawColumn() && $rawText !== null) {
            $row['raw_full_text'] = $rawText;
        }
        db()->insert('transcripts', $row);
    }

    public function persistReport($interviewId, array $analysis, $applicationId = null)
    {
        if ($applicationId === null) {
            $row = db()->fetch('SELECT application_id FROM interviews WHERE id = ?', [$interviewId]);
            $applicationId = $row ? $row['application_id'] : null;
        }

        $reportId = uuid();
        $overall = isset($analysis['overall_score']) ? $analysis['overall_score'] : null;
        if ($overall === null || $overall === '') {
            $overall = $this->deriveOverallScore($analysis);
        }
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
            'overview_strengths' => db_text(isset($analysis['overview']['strengths']) ? $analysis['overview']['strengths'] : ''),
            'overview_weaknesses' => db_text(isset($analysis['overview']['weaknesses']) ? $analysis['overview']['weaknesses'] : ''),
            'overview_red_flags' => db_text(isset($analysis['overview']['red_flags']) ? $analysis['overview']['red_flags'] : ''),
            'overview_team_fit' => db_text(isset($analysis['overview']['team_fit']) ? $analysis['overview']['team_fit'] : ''),
            'overview_cheating_detection' => db_text(isset($analysis['overview']['cheating_detection']) ? $analysis['overview']['cheating_detection'] : ''),
            'overview_qualification_match' => db_text(isset($analysis['overview']['qualification_match']) ? $analysis['overview']['qualification_match'] : ''),
            'overview_logistics' => db_text(isset($analysis['overview']['logistics']) ? $analysis['overview']['logistics'] : ''),
            'overview_follow_up' => db_text(isset($analysis['overview']['follow_up']) ? $analysis['overview']['follow_up'] : ''),
            'overview_recommendation' => db_text(isset($analysis['overview']['recommendation']) ? $analysis['overview']['recommendation'] : ''),
            'raw_analysis_json' => json_encode($analysis),
        ]);

        if (!empty($analysis['criterion_scores'])) {
            foreach ($analysis['criterion_scores'] as $cs) {
                if (!empty($cs['insufficient_evidence']) || !isset($cs['score']) || $cs['score'] === null || $cs['score'] === '') {
                    continue;
                }
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

        db()->update('interviews', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $interviewId]);

        if ($applicationId) {
            db()->update('applications', ['status' => 'completed'], 'id = :id', ['id' => $applicationId]);
        }

        $media = db()->fetch('SELECT TOP 1 id FROM interview_media WHERE interview_id = ? ORDER BY id DESC', [$interviewId]);
        if ($media) {
            db()->update('interview_media', ['processing_status' => 'ready'], 'id = :id', ['id' => $media['id']]);
        }

        return $reportId;
    }

    public function deriveOverallScore($analysis)
    {
        if (empty($analysis['criterion_scores']) || !is_array($analysis['criterion_scores'])) {
            return 0;
        }
        $sum = 0;
        $count = 0;
        foreach ($analysis['criterion_scores'] as $cs) {
            if (!empty($cs['insufficient_evidence']) || !isset($cs['score']) || $cs['score'] === null || $cs['score'] === '') {
                continue;
            }
            $sum += (float) $cs['score'];
            $count++;
        }
        return $count > 0 ? round($sum / $count, 1) : 0;
    }

    private function transcriptsHasRawColumn()
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $row = db()->fetch("SELECT COL_LENGTH('transcripts', 'raw_full_text') AS col_len");
            $has = !empty($row['col_len']);
        } catch (Exception $e) {
            $has = false;
        }
        return $has;
    }
}
