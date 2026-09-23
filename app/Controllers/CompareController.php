<?php

class CompareController
{
    public function show($jobId)
    {
        PermissionService::requireJobAccess($jobId);

        $ids = isset($_GET['ids']) ? $_GET['ids'] : '';
        $applicationIds = array_filter(array_map('trim', explode(',', $ids)));
        $applicationIds = array_slice(array_unique($applicationIds), 0, 4);

        if (count($applicationIds) < 2) {
            flash('error', 'Select at least 2 candidates to compare.');
            redirect(url('candidates?job_id=' . $jobId));
        }

        $job = db()->fetch('SELECT j.*, d.name as department_name FROM jobs j LEFT JOIN departments d ON d.id = j.department_id WHERE j.id = ?', [$jobId]);
        if (!$job) {
            http_response_code(404);
            die('Job not found');
        }

        $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
        $params = array_merge($applicationIds, [$jobId]);
        $candidates = db()->fetchAll(
            "SELECT a.id as application_id, a.decision, a.status, a.is_archived,
                    c.id as candidate_id, c.first_name, c.last_name, c.email, c.years_experience, c.current_company,
                    s.name as stage_name,
                    ir.id as report_id, ir.overall_score, ir.experience_score, ir.culture_score,
                    ir.soft_skills_score, ir.score_label, ir.overview_strengths, ir.overview_weaknesses,
                    ir.overview_recommendation, ir.raw_analysis_json
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             LEFT JOIN job_pipeline_stages s ON s.id = a.current_stage_id
             LEFT JOIN interviews i ON i.application_id = a.id
             LEFT JOIN interview_reports ir ON ir.interview_id = i.id
             WHERE a.id IN ($placeholders) AND a.job_id = ?
             ORDER BY ir.overall_score DESC",
            $params
        );

        foreach ($candidates as &$c) {
            $c['criterion_scores'] = [];
            if (!empty($c['report_id'])) {
                $c['criterion_scores'] = db()->fetchAll(
                    'SELECT criterion_name, score FROM interview_criterion_scores WHERE report_id = ? ORDER BY criterion_name',
                    [$c['report_id']]
                );
            }
            if (!empty($c['raw_analysis_json'])) {
                $c['enhanced'] = json_decode($c['raw_analysis_json'], true);
            }
        }
        unset($c);

        render('candidates.compare', [
            'title' => 'Compare Candidates',
            'job' => $job,
            'candidates' => $candidates,
            'activeNav' => 'candidates',
        ]);
    }
}
