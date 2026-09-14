<?php

class LeaderboardController
{
    public function index()
    {
        $departmentId = isset($_GET['departmentId']) ? $_GET['departmentId'] : '';
        $jobId = isset($_GET['jobId']) ? $_GET['jobId'] : '';
        $stageId = isset($_GET['jobPipelineId']) ? $_GET['jobPipelineId'] : '';
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $departments = db()->fetchAll('SELECT * FROM departments ORDER BY name');
        $jobs = db()->fetchAll("SELECT id, title FROM jobs WHERE status = 'active' ORDER BY title");
        $stages = $jobId ? db()->fetchAll('SELECT id, name FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$jobId]) : [];

        $criteria = [];
        $rows = [];
        if ($jobId) {
            $criteria = db()->fetchAll('SELECT * FROM job_rubric_criteria WHERE job_id = ? AND is_active = 1 ORDER BY sort_order', [$jobId]);

            $where = 'j.id = ? AND ir.id IS NOT NULL';
            $params = [$jobId];
            if ($departmentId) {
                $where .= ' AND j.department_id = ?';
                $params[] = $departmentId;
            }
            if ($stageId) {
                $where .= ' AND i.stage_id = ?';
                $params[] = $stageId;
            }
            if ($search) {
                $where .= ' AND (c.first_name LIKE ? OR c.last_name LIKE ?)';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }

            $rows = db()->fetchAll(
                "SELECT ir.*, c.first_name, c.last_name, i.completed_at, i.id as interview_id
                 FROM interview_reports ir
                 JOIN interviews i ON i.id = ir.interview_id
                 JOIN applications a ON a.id = i.application_id
                 JOIN candidates c ON c.id = a.candidate_id
                 JOIN jobs j ON j.id = a.job_id
                 WHERE $where
                 ORDER BY ir.overall_score DESC",
                $params
            );

            foreach ($rows as &$row) {
                $row['criteria_scores'] = db()->fetchAll(
                    'SELECT criterion_name, score FROM interview_criterion_scores WHERE report_id = ?',
                    [$row['id']]
                );
            }
        }

        render('leaderboard.index', [
            'title' => 'Leaderboard',
            'departments' => $departments,
            'jobs' => $jobs,
            'stages' => $stages,
            'criteria' => $criteria,
            'rows' => $rows,
            'departmentId' => $departmentId,
            'jobId' => $jobId,
            'stageId' => $stageId,
            'search' => $search,
            'activeNav' => 'leaderboard',
        ]);
    }
}
