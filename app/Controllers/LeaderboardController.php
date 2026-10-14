<?php

class LeaderboardController
{
    public function index()
    {
        $perPage = isset($_GET['perPage']) ? max(1, (int) $_GET['perPage']) : 15;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $departmentId = isset($_GET['departmentId']) ? $_GET['departmentId'] : '';
        $jobId = isset($_GET['jobId']) ? $_GET['jobId'] : '';
        $stageId = isset($_GET['jobPipelineId']) ? $_GET['jobPipelineId'] : '';
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $departments = db()->fetchAll('SELECT * FROM departments ORDER BY name');
        $jobWhere = "j.status = 'active'
                OR EXISTS (
                    SELECT 1 FROM applications a
                    JOIN interviews iv ON iv.application_id = a.id
                    JOIN interview_reports irx ON irx.interview_id = iv.id
                    WHERE a.job_id = j.id
                )";
        $jobParams = [];
        list($jobAccessSql, $jobAccessParams) = PermissionService::jobListAccessClause('j');
        $jobWhere .= $jobAccessSql;
        $jobParams = array_merge($jobParams, $jobAccessParams);
        $jobs = db()->fetchAll(
            "SELECT j.id, j.title FROM jobs j WHERE $jobWhere ORDER BY j.title",
            $jobParams
        );
        $stages = $jobId ? db()->fetchAll('SELECT id, name FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$jobId]) : [];

        $criteria = $jobId
            ? db()->fetchAll('SELECT * FROM job_rubric_criteria WHERE job_id = ? AND is_active = 1 ORDER BY sort_order', [$jobId])
            : [];

        $where = ['ir.id IS NOT NULL'];
        $params = [];

        if ($jobId) {
            $where[] = 'j.id = ?';
            $params[] = $jobId;
        }
        if ($departmentId) {
            $where[] = 'j.department_id = ?';
            $params[] = $departmentId;
        }
        if ($stageId) {
            $where[] = 'i.stage_id = ?';
            $params[] = $stageId;
        }
        if ($search !== '') {
            $where[] = '(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        $fromSql = "FROM interview_reports ir
             INNER JOIN (
                 SELECT interview_id, MAX(created_at) AS max_created
                 FROM interview_reports
                 GROUP BY interview_id
             ) latest ON latest.interview_id = ir.interview_id AND latest.max_created = ir.created_at
             JOIN interviews i ON i.id = ir.interview_id
             JOIN applications a ON a.id = i.application_id AND a.is_archived = 0
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             WHERE {$whereSql}";

        $total = (int) db()->fetch("SELECT COUNT(*) as c {$fromSql}", $params)['c'];
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = db()->fetchAll(
            "SELECT ir.*, c.first_name, c.last_name, j.id as job_id, j.title as job_title,
                    i.completed_at, i.id as interview_id
             {$fromSql}
             ORDER BY ir.overall_score DESC, ir.created_at DESC" . sql_page($perPage, $offset),
            $params
        );

        foreach ($rows as &$row) {
            $row['criteria_scores'] = db()->fetchAll(
                'SELECT criterion_name, score FROM interview_criterion_scores WHERE report_id = ?',
                [$row['id']]
            );
        }
        unset($row);

        $criteriaColumns = self::buildCriteriaColumns($criteria, $rows);

        render('leaderboard.index', [
            'title' => 'Leaderboard',
            'departments' => $departments,
            'jobs' => $jobs,
            'stages' => $stages,
            'criteria' => $criteria,
            'criteriaColumns' => $criteriaColumns,
            'rows' => $rows,
            'departmentId' => $departmentId,
            'jobId' => $jobId,
            'stageId' => $stageId,
            'search' => $search,
            'perPage' => $perPage,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
            'activeNav' => 'leaderboard',
        ]);
    }

    /**
     * Rubric order for a job, plus any skill/criterion scores stored on reports.
     *
     * @param array<int, array{name:string}> $rubricCriteria
     * @param array<int, array{criteria_scores:array}> $rows
     * @return string[]
     */
    private static function buildCriteriaColumns(array $rubricCriteria, array $rows)
    {
        $columns = [];
        $seen = [];
        foreach ($rubricCriteria as $cr) {
            $name = trim((string) ($cr['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $columns[] = $name;
        }

        $extras = [];
        foreach ($rows as $row) {
            if (empty($row['criteria_scores']) || !is_array($row['criteria_scores'])) {
                continue;
            }
            foreach ($row['criteria_scores'] as $cs) {
                $name = trim((string) ($cs['criterion_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $key = strtolower($name);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $extras[$key] = $name;
                }
            }
        }
        if (!empty($extras)) {
            natcasesort($extras);
            foreach ($extras as $name) {
                $columns[] = $name;
            }
        }

        return $columns;
    }
}
