<?php

class JobController
{
    private function requireJobManage($jobId)
    {
        PermissionService::requireManageJob($jobId);
    }

    private function masterData()
    {
        $skillsPool = [];
        try {
            $skillsPool = db()->fetchAll('SELECT * FROM skill_tags WHERE is_active = 1 ORDER BY sort_order, name');
        } catch (Exception $e) {
        }

        $jobTitles = [];
        try {
            $jobTitles = db()->fetchAll('SELECT * FROM job_titles WHERE is_active = 1 ORDER BY sort_order, name');
        } catch (Exception $e) {
        }

        return [
            'departments' => db()->fetchAll('SELECT * FROM departments WHERE is_active = 1 ORDER BY name'),
            'job_titles' => $jobTitles,
            'seniority' => db()->fetchAll('SELECT * FROM seniority_levels ORDER BY sort_order'),
            'skills_pool' => $skillsPool,
            'company_about_default' => company_about_effective(),
            'countries' => db()->fetchAll('SELECT * FROM countries ORDER BY name'),
            'work_modes' => db()->fetchAll('SELECT * FROM work_modes ORDER BY name'),
            'users' => db()->fetchAll('SELECT id, first_name, last_name, email, initials, role FROM users WHERE is_active = 1 ORDER BY first_name'),
        ];
    }

    private function parseSkillsFromPost(array $post)
    {
        $skills = [];
        if (!empty($post['key_skills_selected']) && is_array($post['key_skills_selected'])) {
            foreach ($post['key_skills_selected'] as $s) {
                $s = trim($s);
                if ($s !== '') {
                    $skills[] = $s;
                }
            }
        }
        if (!empty($post['key_skills'])) {
            foreach ($this->splitSkillText($post['key_skills']) as $s) {
                if ($s !== '' && !in_array($s, $skills, true)) {
                    $skills[] = $s;
                }
            }
        }
        $skills = $this->expandOversizedSkills($skills);
        if ($skills !== []) {
            $skills = SkillTagService::ensureInPool($skills);
        }

        return $skills;
    }

    private function splitSkillText($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/\s*,\s*/', $text);
        if (count($parts) === 1 && mb_strlen($text) > 120) {
            $parts = preg_split('/[\r\n;|•]+/', $text);
        }
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    private function expandOversizedSkills(array $skills)
    {
        $out = [];
        foreach ($skills as $s) {
            $s = trim($s);
            if ($s === '') {
                continue;
            }
            if (mb_strlen($s) > 200) {
                foreach ($this->splitSkillText($s) as $part) {
                    if ($part !== '' && !in_array($part, $out, true)) {
                        $out[] = $part;
                    }
                }
                continue;
            }
            if (!in_array($s, $out, true)) {
                $out[] = $s;
            }
        }
        return $out;
    }

    private function enrichJobForForm($job)
    {
        if (!$job) {
            return null;
        }
        $decoded = json_decode($job['key_skills'], true) ?: [];
        $job['skills_str'] = implode(', ', $decoded);
        $job['skills_selected'] = $decoded;
        $job['hiring_managers'] = db()->fetchAll('SELECT user_id FROM job_hiring_managers WHERE job_id = ?', [$job['id']]);
        $job['interviewers'] = db()->fetchAll('SELECT user_id FROM job_interviewers WHERE job_id = ?', [$job['id']]);
        $job['hiring_team_selected'] = [];
        foreach ($job['hiring_managers'] as $hm) {
            $job['hiring_team_selected'][] = 'user:' . $hm['user_id'];
        }
        try {
            $job['external_hm'] = db()->fetchAll(
                "SELECT name, email FROM job_external_contacts WHERE job_id = ? AND contact_role = 'hiring_manager' ORDER BY id",
                [$job['id']]
            );
            $job['external_iv'] = db()->fetchAll(
                "SELECT name, email FROM job_external_contacts WHERE job_id = ? AND contact_role = 'interviewer' ORDER BY id",
                [$job['id']]
            );
        } catch (Exception $e) {
            $job['external_hm'] = [];
            $job['external_iv'] = [];
        }
        foreach ($job['external_hm'] as $ex) {
            if (!empty($ex['email'])) {
                $keka = db()->fetch('SELECT id FROM keka_employees WHERE LOWER(email) = LOWER(?)', [$ex['email']]);
                if ($keka) {
                    $job['hiring_team_selected'][] = 'keka:' . $keka['id'];
                } else {
                    $job['hiring_team_selected'][] = 'ext:' . $ex['email'];
                }
            } elseif (trim($ex['name'] ?? '') !== '') {
                $job['hiring_team_selected'][] = 'extn:' . rawurlencode(trim($ex['name']));
            }
        }
        $job['interviewer_team_selected'] = [];
        foreach ($job['interviewers'] as $iv) {
            $job['interviewer_team_selected'][] = 'user:' . $iv['user_id'];
        }
        foreach ($job['external_iv'] as $ex) {
            if (!empty($ex['email'])) {
                $keka = db()->fetch('SELECT id FROM keka_employees WHERE LOWER(email) = LOWER(?)', [$ex['email']]);
                if ($keka) {
                    $job['interviewer_team_selected'][] = 'keka:' . $keka['id'];
                } else {
                    $job['interviewer_team_selected'][] = 'ext:' . $ex['email'];
                }
            } elseif (trim($ex['name'] ?? '') !== '') {
                $job['interviewer_team_selected'][] = 'extn:' . rawurlencode(trim($ex['name']));
            }
        }
        // Externals are shown in the directory multiselect; keep manual rows empty unless adding new.
        $job['external_iv'] = [];
        return $job;
    }

    private function titleFieldsFromPost(array $post)
    {
        $jobTitleId = !empty($post['job_title_id']) ? (int) $post['job_title_id'] : null;
        $typedTitle = trim($post['title'] ?? '');
        $title = $typedTitle;

        if ($title === '' && $jobTitleId) {
            try {
                $row = db()->fetch('SELECT name FROM job_titles WHERE id = ? AND is_active = 1', [$jobTitleId]);
                if ($row) {
                    $title = trim($row['name']);
                }
            } catch (Exception $e) {
            }
        }

        if ($title === '') {
            $title = 'Untitled Role';
        }

        return [
            'title' => $title,
            // Free-form title wins; catalog id only when title came from the backup dropdown alone.
            'job_title_id' => ($typedTitle === '' && $jobTitleId) ? $jobTitleId : null,
        ];
    }

    /**
     * Portal users (job_hiring_managers) + directory-only rows (job_external_contacts).
     *
     * @param array<int,string> $jobIds
     * @return array<string, array<int, array{first_name:string,last_name:string,initials:?string}>>
     */
    private function hiringManagersForJobs(array $jobIds)
    {
        $byJob = [];
        foreach ($jobIds as $id) {
            $byJob[$id] = [];
        }
        if ($jobIds === []) {
            return $byJob;
        }

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $portal = db()->fetchAll(
            "SELECT jhm.job_id, u.first_name, u.last_name, u.initials
             FROM job_hiring_managers jhm
             JOIN users u ON u.id = jhm.user_id
             WHERE jhm.job_id IN ($placeholders)",
            $jobIds
        );
        foreach ($portal as $row) {
            $byJob[$row['job_id']][] = [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'] ?? '',
                'initials' => $row['initials'],
            ];
        }

        try {
            $external = db()->fetchAll(
                "SELECT job_id, name, email FROM job_external_contacts
                 WHERE contact_role = 'hiring_manager' AND job_id IN ($placeholders)",
                $jobIds
            );
            foreach ($external as $row) {
                $label = trim((string) ($row['name'] ?: $row['email'] ?: ''));
                if ($label === '') {
                    continue;
                }
                $parts = preg_split('/\s+/', $label, 2);
                $byJob[$row['job_id']][] = [
                    'first_name' => $parts[0],
                    'last_name' => isset($parts[1]) ? $parts[1] : '',
                    'initials' => null,
                ];
            }
        } catch (Exception $e) {
        }

        return $byJob;
    }

    public function index()
    {
        $status = isset($_GET['status']) ? $_GET['status'] : 'active';
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $perPage = isset($_GET['perPage']) ? (int) $_GET['perPage'] : 15;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];
        if ($status === 'active') {
            $where .= " AND j.status = 'active'";
        } elseif ($status === 'inactive') {
            $where .= " AND j.status = 'inactive'";
        } elseif ($status === 'archived') {
            $where .= " AND j.status = 'archived'";
        }
        if ($search) {
            $where .= ' AND (j.title LIKE ? OR ci.name LIKE ? OR st.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $stateFilter = isset($_GET['state_id']) ? (int) $_GET['state_id'] : 0;
        if ($stateFilter > 0) {
            $where .= ' AND j.state_id = ?';
            $params[] = $stateFilter;
        }

        $cityFilter = isset($_GET['city_id']) ? (int) $_GET['city_id'] : 0;
        if ($cityFilter > 0) {
            $where .= ' AND j.city_id = ?';
            $params[] = $cityFilter;
        }

        list($accessSql, $accessParams) = PermissionService::jobListAccessClause('j');
        $where .= $accessSql;
        $params = array_merge($params, $accessParams);

        $scope = isset($_GET['scope']) ? $_GET['scope'] : '';
        if ($scope === 'mine') {
            $where .= ' AND j.created_by = ?';
            $params[] = auth_id();
        }

        if (!empty($_GET['created_by']) && PermissionService::isAdminRole()) {
            $where .= ' AND j.created_by = ?';
            $params[] = (int) $_GET['created_by'];
        }

        $order = isset($_GET['order']) ? $_GET['order'] : 'created_desc';
        $orderSql = 'j.created_at DESC';
        if ($order === 'title') {
            $orderSql = 'j.title ASC';
        } elseif ($order === 'creator') {
            $orderSql = 'creator.last_name ASC, creator.first_name ASC, j.title ASC';
        } elseif ($order === 'location') {
            $orderSql = 'st.name ASC, ci.name ASC, j.title ASC';
        }

        $jobJoins = '
             LEFT JOIN cities ci ON ci.id = j.city_id
             LEFT JOIN states st ON st.id = j.state_id';

        $total = db()->fetch("SELECT COUNT(*) as c FROM jobs j $jobJoins WHERE $where", $params)['c'];
        $jobs = db()->fetchAll(
            "SELECT j.*, d.name as department_name, s.name as seniority_name,
                    ci.name as city_name, st.name as state_name,
                    creator.first_name AS creator_first_name, creator.last_name AS creator_last_name,
                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) as applicants,
                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status IN ('completed','scheduled')) as candidates,
                    (SELECT COUNT(*) FROM interviews i JOIN applications a ON a.id = i.application_id WHERE a.job_id = j.id AND i.status = 'completed') as interviews_done
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN seniority_levels s ON s.id = j.seniority_id
             $jobJoins
             LEFT JOIN users creator ON creator.id = j.created_by
             WHERE $where ORDER BY $orderSql" . sql_page($perPage, $offset),
            $params
        );

        $hmByJob = $this->hiringManagersForJobs(array_column($jobs, 'id'));
        foreach ($jobs as &$job) {
            $job['hiring_managers'] = $hmByJob[$job['id']] ?? [];
            $job['can_manage'] = PermissionService::canManageJob($job['id']);
        }
        unset($job);

        $teamUsers = [];
        if (PermissionService::isAdminRole()) {
            $teamUsers = db()->fetchAll(
                'SELECT id, first_name, last_name FROM users WHERE is_active = 1 ORDER BY first_name, last_name'
            );
        }

        $locationStates = db()->fetchAll(
            "SELECT DISTINCT st.id, st.name
             FROM jobs j
             INNER JOIN states st ON st.id = j.state_id
             LEFT JOIN cities ci ON ci.id = j.city_id
             WHERE $where
             ORDER BY st.name",
            $params
        );
        $locationCities = [];
        if ($stateFilter > 0) {
            $locationCities = db()->fetchAll(
                "SELECT DISTINCT ci.id, ci.name
                 FROM jobs j
                 INNER JOIN cities ci ON ci.id = j.city_id
                 LEFT JOIN states st ON st.id = j.state_id
                 WHERE $where
                 ORDER BY ci.name",
                $params
            );
        }

        render('jobs.index', [
            'title' => 'Job Roles',
            'jobs' => $jobs,
            'status' => $status,
            'search' => $search,
            'scope' => $scope,
            'order' => $order,
            'stateFilter' => $stateFilter,
            'cityFilter' => $cityFilter,
            'locationStates' => $locationStates,
            'locationCities' => $locationCities,
            'teamUsers' => $teamUsers,
            'perPage' => $perPage,
            'page' => $page,
            'total' => $total,
            'totalPages' => max(1, ceil($total / $perPage)),
            'activeNav' => 'jobs',
        ]);
    }

    public function create()
    {
        $job = null;
        if (!empty($_SESSION['wizard_job_id'])) {
            $row = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$_SESSION['wizard_job_id']]);
            if ($row) {
                $job = $this->enrichJobForForm($row);
            }
        }
        render('jobs.create', array_merge($this->masterData(), [
            'title' => 'Add New Job Role',
            'job' => $job,
            'step' => 1,
            'mode' => 'create',
            'activeNav' => 'jobs',
        ]));
    }

    public function storeStep1()
    {
        verify_csrf();
        if (!$this->hasHiringTeam($_POST)) {
            flash('error', 'Select at least one hiring manager (team member or external).');
            redirect(url('jobs/create'));
        }

        $skills = $this->parseSkillsFromPost($_POST);
        if (empty($skills)) {
            flash('error', 'Select or enter at least one key skill.');
            redirect(url('jobs/create'));
        }
        $jobId = !empty($_SESSION['wizard_job_id']) ? $_SESSION['wizard_job_id'] : uuid();
        $exists = db()->fetch('SELECT id FROM jobs WHERE id = ?', [$jobId]);

        $fields = array_merge($this->titleFieldsFromPost($_POST), [
            'department_id' => $_POST['department_id'] ?: null,
            'seniority_id' => $_POST['seniority_id'] ?: null,
            'country_id' => $_POST['country_id'] ?: null,
            'state_id' => $_POST['state_id'] ?: null,
            'city_id' => $_POST['city_id'] ?: null,
            'num_hires' => (int) $_POST['num_hires'],
            'work_mode_id' => $_POST['work_mode_id'] ?: null,
            'about_company' => $_POST['about_company'],
            'key_responsibility' => $_POST['key_responsibility'],
            'qualifications' => $_POST['qualifications'],
            'must_have_criteria' => $_POST['must_have_criteria'],
            'key_skills' => json_encode($skills),
            'salary_currency' => $_POST['salary_currency'] ?: 'INR',
            'salary_min' => $_POST['salary_min'] !== '' ? $_POST['salary_min'] : null,
            'salary_max' => $_POST['salary_max'] !== '' ? $_POST['salary_max'] : null,
            'salary_period' => $_POST['salary_period'] ?: 'annual',
            'hide_salary' => isset($_POST['hide_salary']) ? 1 : 0,
        ]);

        if ($exists) {
            $this->requireJobManage($jobId);
            db()->update('jobs', $fields, 'id = :id', ['id' => $jobId]);
        } else {
            db()->insert('jobs', array_merge($fields, [
                'id' => $jobId,
                'created_by' => auth_id(),
            ]));
            $this->createDefaultPipeline($jobId);
            $this->createDefaultRubrics($jobId, $skills);
        }

        $this->syncManagers($jobId, $_POST);
        $this->syncExternalContacts($jobId, $_POST);

        $_SESSION['wizard_job_id'] = $jobId;
        redirect(url('jobs/create/pipeline'));
    }

    public function createPipeline()
    {
        $jobId = $this->wizardJobId();
        $stages = db()->fetchAll('SELECT * FROM job_pipeline_stages WHERE job_id = ? AND is_active = 1 ORDER BY sort_order', [$jobId]);
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        render('jobs.pipeline', [
            'title' => 'Pipeline',
            'job' => $job,
            'stages' => $stages,
            'step' => 2,
            'mode' => 'create',
            'activeNav' => 'jobs',
        ]);
    }

    public function storePipeline()
    {
        verify_csrf();
        $jobId = $this->wizardJobId();
        $this->savePipelineStages($jobId, $_POST);
        redirect(url('jobs/create/questions'));
    }

    public function createQuestions()
    {
        $jobId = $this->wizardJobId();
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        $questions = db()->fetchAll('SELECT * FROM job_interview_questions WHERE job_id = ? ORDER BY sort_order', [$jobId]);
        render('jobs.questions', [
            'title' => 'Interview Question',
            'job' => $job,
            'questions' => $questions,
            'step' => 3,
            'mode' => 'create',
            'activeNav' => 'jobs',
        ]);
    }

    public function storeQuestions()
    {
        verify_csrf();
        $jobId = $this->wizardJobId();
        if (!empty($_POST['skip_questions'])) {
            redirect(url('jobs/create/draft-message'));
        }
        db()->query('DELETE FROM job_interview_questions WHERE job_id = ?', [$jobId]);
        if (!empty($_POST['questions'])) {
            foreach ($_POST['questions'] as $idx => $q) {
                $q = trim($q);
                if ($q) {
                    db()->insert('job_interview_questions', [
                        'job_id' => $jobId,
                        'question_text' => $q,
                        'sort_order' => $idx,
                    ]);
                }
            }
        }
        redirect(url('jobs/create/draft-message'));
    }

    public function createDraftMessage()
    {
        $jobId = $this->wizardJobId();
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        $templates = $this->getTemplates($jobId);
        render('jobs.draft_message', [
            'title' => 'Draft Message',
            'job' => $job,
            'templates' => $templates,
            'step' => 4,
            'mode' => 'create',
            'activeNav' => 'jobs',
        ]);
    }

    public function storeDraftMessage()
    {
        verify_csrf();
        $jobId = $this->wizardJobId();
        $this->saveTemplates($jobId, $_POST);
        flash('success', 'Job role saved. Add candidates or finish.');
        redirect(url('jobs/create/candidates'));
    }

    public function createCandidates()
    {
        $jobId = $this->wizardJobId();
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        $stages = db()->fetchAll(
            "SELECT id, name FROM job_pipeline_stages WHERE job_id = ? AND stage_type = 'applicant_pool' ORDER BY sort_order",
            [$jobId]
        );
        $defaultStage = $stages[0]['id'] ?? db()->fetch(
            'SELECT TOP 1 id FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order',
            [$jobId]
        )['id'] ?? '';
        $naukriId = null;
        try {
            $src = db()->fetch("SELECT id FROM candidate_sources WHERE name = N'Naukri'");
            $naukriId = $src ? $src['id'] : null;
        } catch (Exception $e) {
        }
        render('jobs.wizard_candidates', [
            'title' => 'Add Candidate(s)',
            'job' => $job,
            'step' => 5,
            'mode' => 'create',
            'pipelineStages' => db()->fetchAll('SELECT id, name FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$jobId]),
            'defaultStageId' => $defaultStage,
            'sources' => db()->fetchAll('SELECT * FROM candidate_sources ORDER BY name'),
            'defaultSourceId' => $naukriId,
            'activeNav' => 'jobs',
        ]);
    }

    public function storeWizardCandidates()
    {
        verify_csrf();
        $jobId = $this->wizardJobId();

        if (!empty($_POST['skip_candidates'])) {
            unset($_SESSION['wizard_job_id']);
            flash('success', 'Job role created successfully.');
            redirect(url('jobs/' . $jobId . '/interviews'));
        }

        $stageId = $_POST['pipeline_id'] ?? '';
        if (!$stageId) {
            flash('error', 'Select a pipeline stage.');
            redirect(url('jobs/create/candidates'));
        }

        $_POST['job_id'] = $jobId;
        $_POST['pipeline_id'] = $stageId;
        unset($_SESSION['wizard_job_id']);
        $controller = new CandidateController();
        $controller->storeFromWizard($jobId);
    }

    public function edit($id)
    {
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        if (!$job) {
            http_response_code(404);
            die('Job not found');
        }
        $this->requireJobManage($id);
        $job = $this->enrichJobForForm($job);
        render('jobs.create', array_merge($this->masterData(), [
            'title' => 'Edit Job Role',
            'job' => $job,
            'step' => 1,
            'mode' => 'edit',
            'activeNav' => 'jobs',
        ]));
    }

    public function updateStep1($id)
    {
        verify_csrf();
        $this->requireJobManage($id);
        if (!$this->hasHiringTeam($_POST)) {
            flash('error', 'Select at least one hiring manager (team member or external).');
            redirect(url('jobs/edit/' . $id));
        }
        $skills = $this->parseSkillsFromPost($_POST);
        if (empty($skills)) {
            flash('error', 'Select or enter at least one key skill.');
            redirect(url('jobs/edit/' . $id));
        }
        db()->update('jobs', array_merge($this->titleFieldsFromPost($_POST), [
            'department_id' => $_POST['department_id'] ?: null,
            'seniority_id' => $_POST['seniority_id'] ?: null,
            'country_id' => $_POST['country_id'] ?: null,
            'state_id' => $_POST['state_id'] ?: null,
            'city_id' => $_POST['city_id'] ?: null,
            'num_hires' => (int) $_POST['num_hires'],
            'work_mode_id' => $_POST['work_mode_id'] ?: null,
            'about_company' => $_POST['about_company'],
            'key_responsibility' => $_POST['key_responsibility'],
            'qualifications' => $_POST['qualifications'],
            'must_have_criteria' => $_POST['must_have_criteria'],
            'key_skills' => json_encode($skills),
            'salary_currency' => $_POST['salary_currency'] ?: 'INR',
            'salary_min' => $_POST['salary_min'] ?: null,
            'salary_max' => $_POST['salary_max'] ?: null,
            'salary_period' => $_POST['salary_period'] ?: 'annual',
            'hide_salary' => isset($_POST['hide_salary']) ? 1 : 0,
        ]), 'id = :id', ['id' => $id]);
        $this->syncManagers($id, $_POST);
        $this->syncExternalContacts($id, $_POST);
        redirect(url('jobs/edit/' . $id . '/pipeline'));
    }

    public function editPipeline($id)
    {
        $this->requireJobManage($id);
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        $stages = db()->fetchAll('SELECT * FROM job_pipeline_stages WHERE job_id = ? AND is_active = 1 ORDER BY sort_order', [$id]);
        render('jobs.pipeline', ['title' => 'Edit Pipeline', 'job' => $job, 'stages' => $stages, 'step' => 2, 'mode' => 'edit', 'activeNav' => 'jobs']);
    }

    public function updatePipeline($id)
    {
        verify_csrf();
        $this->requireJobManage($id);
        $this->savePipelineStages($id, $_POST);
        redirect(url('jobs/edit/' . $id . '/custom-question'));
    }

    public function editQuestions($id)
    {
        $this->requireJobManage($id);
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        $questions = db()->fetchAll('SELECT * FROM job_interview_questions WHERE job_id = ? ORDER BY sort_order', [$id]);
        $rubric = db()->fetchAll('SELECT * FROM job_rubric_criteria WHERE job_id = ? ORDER BY sort_order', [$id]);
        render('jobs.questions', [
            'title' => 'Edit Interview Question',
            'job' => $job,
            'questions' => $questions,
            'rubric' => $rubric,
            'step' => 3,
            'mode' => 'edit',
            'activeNav' => 'jobs',
        ]);
    }

    public function updateQuestions($id)
    {
        verify_csrf();
        $this->requireJobManage($id);
        db()->query('DELETE FROM job_interview_questions WHERE job_id = ?', [$id]);
        if (!empty($_POST['questions'])) {
            foreach ($_POST['questions'] as $idx => $q) {
                $q = trim($q);
                if ($q) {
                    db()->insert('job_interview_questions', ['job_id' => $id, 'question_text' => $q, 'sort_order' => $idx]);
                }
            }
        }
        $this->saveRubricCriteria($id, $_POST);
        redirect(url('jobs/edit/' . $id . '/draft-message'));
    }

    public function editDraftMessage($id)
    {
        $this->requireJobManage($id);
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        $templates = $this->getTemplates($id);
        render('jobs.draft_message', ['title' => 'Edit Draft Message', 'job' => $job, 'templates' => $templates, 'step' => 4, 'mode' => 'edit', 'activeNav' => 'jobs']);
    }

    public function updateDraftMessage($id)
    {
        verify_csrf();
        $this->requireJobManage($id);
        $this->saveTemplates($id, $_POST);
        flash('success', 'Job updated successfully.');
        redirect(url('jobs/' . $id . '/interviews'));
    }

    public function updateStatus($id)
    {
        verify_csrf();
        $this->requireJobManage($id);
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
            flash('error', 'Invalid status.');
            redirect(url('jobs/' . $id . '/interviews'));
        }
        db()->update('jobs', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        flash('success', 'Job status updated to ' . $status . '.');
        redirect(url('jobs/' . $id . '/interviews'));
    }

    public function show($id)
    {
        PermissionService::requireJobAccess($id);
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'interviews';
        $job = db()->fetch(
            "SELECT j.*, d.name as department_name, s.name as seniority_name,
                    ci.name as city_name, st.name as state_name, co.name as country_name,
                    creator.first_name AS creator_first_name, creator.last_name AS creator_last_name,
                    creator.email AS creator_email
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN seniority_levels s ON s.id = j.seniority_id
             LEFT JOIN cities ci ON ci.id = j.city_id
             LEFT JOIN states st ON st.id = j.state_id
             LEFT JOIN countries co ON co.id = j.country_id
             LEFT JOIN users creator ON creator.id = j.created_by
             WHERE j.id = ?",
            [$id]
        );
        if (!$job) {
            http_response_code(404);
            die('Job not found');
        }

        $job['managers'] = db()->fetchAll(
            'SELECT u.initials, u.first_name, u.last_name FROM job_hiring_managers jhm JOIN users u ON u.id = jhm.user_id WHERE jhm.job_id = ?',
            [$id]
        );

        if ($tab === 'applicant-pool') {
            $applicants = db()->fetchAll(
                "SELECT a.*, c.first_name, c.last_name, c.current_company, c.current_location, c.years_experience
                 FROM applications a JOIN candidates c ON c.id = a.candidate_id
                 WHERE a.job_id = ?" . sql_active_applications_only('a') . ' ORDER BY a.application_date DESC',
                [$id]
            );
            render('jobs.show_applicant_pool', compact('job', 'applicants') + ['title' => $job['title'], 'activeNav' => 'jobs', 'tab' => $tab]);
            return;
        }

        $stageFilter = isset($_GET['stage_id']) ? trim($_GET['stage_id']) : '';
        $focusInterviewId = isset($_GET['focus']) ? trim($_GET['focus']) : '';
        $pipelineStages = db()->fetchAll(
            'SELECT id, name FROM job_pipeline_stages WHERE job_id = ? AND is_active = 1 ORDER BY sort_order',
            [$id]
        );

        $params = [$id];
        $stageSql = '';
        if ($stageFilter !== '') {
            $stageSql = ' AND i.stage_id = ?';
            $params[] = $stageFilter;
        }

        $interviews = db()->fetchAll(
            "SELECT i.*, c.first_name, c.last_name, c.id as candidate_id,
                    a.decision, a.current_stage_id,
                    ir.id as report_id, ir.overall_score, ir.score_label,
                    ps.name as stage_name, ps.stage_type,
                    CASE WHEN i.stage_id = a.current_stage_id THEN 1 ELSE 0 END AS is_current_round
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             LEFT JOIN job_pipeline_stages ps ON ps.id = i.stage_id
             OUTER APPLY (
                SELECT TOP 1 ir2.id, ir2.overall_score, ir2.score_label
                FROM interview_reports ir2
                WHERE ir2.interview_id = i.id
                ORDER BY ir2.created_at DESC
             ) ir
             WHERE a.job_id = ?" . sql_active_applications_only('a') . "{$stageSql}
             ORDER BY is_current_round DESC, ps.sort_order DESC, i.created_at DESC",
            $params
        );

        foreach ($interviews as &$ivRow) {
            if (($ivRow['status'] ?? '') === 'processing' && empty($ivRow['report_id'])) {
                $ivRow['processing_hint'] = InterviewWorkflowService::processingHint($ivRow);
            }
        }
        unset($ivRow);

        render('jobs.show_interviews', [
            'title' => $job['title'],
            'job' => $job,
            'interviews' => $interviews,
            'pipelineStages' => $pipelineStages,
            'stageFilter' => $stageFilter,
            'focusInterviewId' => $focusInterviewId,
            'tab' => $tab,
            'canManageJob' => PermissionService::canManageJob($id),
            'activeNav' => 'jobs',
        ]);
    }

    private function wizardJobId()
    {
        if (empty($_SESSION['wizard_job_id'])) {
            redirect(url('jobs/create'));
        }
        return $_SESSION['wizard_job_id'];
    }

    private function hasHiringTeam(array $post)
    {
        if (!empty($post['hiring_team']) && is_array($post['hiring_team'])) {
            return true;
        }
        if (!empty($post['hiring_managers']) && is_array($post['hiring_managers'])) {
            return true;
        }
        $names = $post['external_hm_name'] ?? [];
        if (is_array($names)) {
            foreach ($names as $n) {
                if (trim($n) !== '') {
                    return true;
                }
            }
        }
        return false;
    }

    private function syncManagers($jobId, $post)
    {
        db()->query('DELETE FROM job_hiring_managers WHERE job_id = ?', [$jobId]);
        try {
            db()->query("DELETE FROM job_external_contacts WHERE job_id = ? AND contact_role = 'hiring_manager'", [$jobId]);
        } catch (Exception $e) {
        }

        $dir = new EmployeeDirectoryService();
        $team = !empty($post['hiring_team']) && is_array($post['hiring_team']) ? $post['hiring_team'] : [];
        foreach ($team as $token) {
            $dir->attachToJob($jobId, trim($token), 'hiring_manager');
        }

        if (empty($team) && !empty($post['hiring_managers'])) {
            foreach ($post['hiring_managers'] as $uid) {
                if ($uid) {
                    db()->insert('job_hiring_managers', ['job_id' => $jobId, 'user_id' => $uid]);
                }
            }
        }

        $this->insertExternalContacts($jobId, 'hiring_manager', $post['external_hm_name'] ?? [], $post['external_hm_email'] ?? []);
    }

    private function syncExternalContacts($jobId, $post)
    {
        db()->query('DELETE FROM job_interviewers WHERE job_id = ?', [$jobId]);
        try {
            db()->query("DELETE FROM job_external_contacts WHERE job_id = ? AND contact_role = 'interviewer'", [$jobId]);
        } catch (Exception $e) {
        }

        $dir = new EmployeeDirectoryService();
        $team = !empty($post['interviewer_team']) && is_array($post['interviewer_team']) ? $post['interviewer_team'] : [];
        foreach ($team as $token) {
            $dir->attachToJob($jobId, trim($token), 'interviewer');
        }
        if (empty($team) && !empty($post['interviewers'])) {
            foreach ($post['interviewers'] as $uid) {
                if ($uid) {
                    db()->insert('job_interviewers', ['job_id' => $jobId, 'user_id' => $uid]);
                }
            }
        }

        $this->insertExternalContacts($jobId, 'interviewer', $post['external_iv_name'] ?? [], $post['external_iv_email'] ?? []);
    }

    private function insertExternalContacts($jobId, $role, $names, $emails)
    {
        if (!is_array($names)) {
            return;
        }
        foreach ($names as $i => $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $email = isset($emails[$i]) ? trim($emails[$i]) : null;
            try {
                db()->insert('job_external_contacts', [
                    'job_id' => $jobId,
                    'contact_role' => $role,
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                ]);
            } catch (Exception $e) {
            }
        }
    }

    private function createDefaultPipeline($jobId)
    {
        $defaults = [
            ['name' => 'Applicant Pool', 'type' => 'applicant_pool', 'order' => 0, 'system' => 1],
            ['name' => 'Round 1', 'type' => 'round', 'order' => 1, 'system' => 0],
            ['name' => 'Round 2', 'type' => 'round', 'order' => 2, 'system' => 0],
            ['name' => 'Offer', 'type' => 'offer', 'order' => 3, 'system' => 1],
        ];
        foreach ($defaults as $d) {
            db()->insert('job_pipeline_stages', [
                'id' => uuid(),
                'job_id' => $jobId,
                'name' => $d['name'],
                'stage_type' => $d['type'],
                'sort_order' => $d['order'],
                'is_system' => $d['system'],
            ]);
        }
    }

    private function createDefaultRubrics($jobId, $skills)
    {
        $criteria = !empty($skills) ? $skills : ['Relevant Experience', 'Cultural Fit', 'Technical Skills'];
        foreach ($criteria as $idx => $name) {
            $fields = rubric_criterion_fields($name);
            db()->insert('job_rubric_criteria', [
                'job_id' => $jobId,
                'name' => $fields['name'],
                'description' => $fields['description'],
                'sort_order' => $idx,
            ]);
        }
    }

    private function saveRubricCriteria($jobId, array $post)
    {
        $names = isset($post['rubric_name']) && is_array($post['rubric_name']) ? $post['rubric_name'] : [];
        $descs = isset($post['rubric_description']) && is_array($post['rubric_description']) ? $post['rubric_description'] : [];
        $ids = isset($post['rubric_id']) && is_array($post['rubric_id']) ? $post['rubric_id'] : [];
        if (empty($names)) {
            return;
        }
        $keptIds = [];
        foreach ($names as $idx => $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $description = isset($descs[$idx]) ? trim($descs[$idx]) : '';
            $fields = rubric_criterion_fields($name, $description);
            $rid = isset($ids[$idx]) ? (int) $ids[$idx] : 0;
            if ($rid > 0) {
                db()->update('job_rubric_criteria', [
                    'name' => $fields['name'],
                    'description' => $fields['description'],
                    'sort_order' => $idx,
                    'is_active' => 1,
                ], 'id = :id AND job_id = :jid', ['id' => $rid, 'jid' => $jobId]);
                $keptIds[] = $rid;
            } else {
                $newId = db()->insert('job_rubric_criteria', [
                    'job_id' => $jobId,
                    'name' => $fields['name'],
                    'description' => $fields['description'],
                    'sort_order' => $idx,
                    'is_active' => 1,
                ]);
                if ($newId) {
                    $keptIds[] = (int) $newId;
                }
            }
        }
        if (!empty($keptIds)) {
            $placeholders = implode(',', array_fill(0, count($keptIds), '?'));
            db()->query(
                'UPDATE job_rubric_criteria SET is_active = 0 WHERE job_id = ? AND id NOT IN (' . $placeholders . ')',
                array_merge([$jobId], $keptIds)
            );
        }
    }

    private function savePipelineStages($jobId, $post)
    {
        $order = [];
        if (!empty($post['stage_order']) && is_array($post['stage_order'])) {
            $order = array_values(array_filter(array_map('trim', $post['stage_order'])));
        } elseif (!empty($post['stages']) && is_array($post['stages'])) {
            foreach ($post['stages'] as $stage) {
                if (!empty($stage['id'])) {
                    $order[] = $stage['id'];
                }
            }
        }
        if (empty($order)) {
            return;
        }

        $namesById = [];
        if (!empty($post['stages'])) {
            foreach ($post['stages'] as $stage) {
                if (!empty($stage['id'])) {
                    $namesById[$stage['id']] = trim($stage['name'] ?? '');
                }
            }
        }

        foreach ($order as $idx => $stageId) {
            $row = db()->fetch('SELECT is_system FROM job_pipeline_stages WHERE id = ? AND job_id = ?', [$stageId, $jobId]);
            if (!$row) {
                continue;
            }
            $update = ['sort_order' => $idx, 'is_active' => 1];
            if (!(int) $row['is_system'] && isset($namesById[$stageId]) && $namesById[$stageId] !== '') {
                $update['name'] = $namesById[$stageId];
            }
            db()->update('job_pipeline_stages', $update, 'id = :id AND job_id = :jid', ['id' => $stageId, 'jid' => $jobId]);
        }

        if (!empty($post['deleted_stage_ids']) && is_array($post['deleted_stage_ids'])) {
            foreach ($post['deleted_stage_ids'] as $stageId) {
                $stageId = trim($stageId);
                if ($stageId === '') {
                    continue;
                }
                $row = db()->fetch('SELECT is_system FROM job_pipeline_stages WHERE id = ? AND job_id = ?', [$stageId, $jobId]);
                if (!$row || (int) $row['is_system']) {
                    continue;
                }
                PipelineStageService::reassignCandidatesOffStage($jobId, $stageId);
                db()->update('job_pipeline_stages', ['is_active' => 0], 'id = :id AND job_id = :jid', ['id' => $stageId, 'jid' => $jobId]);
            }
        }

        if (!empty($post['new_stages'])) {
            $maxOrder = count($order);
            foreach ($post['new_stages'] as $name) {
                $name = trim($name);
                if ($name) {
                    db()->insert('job_pipeline_stages', [
                        'id' => uuid(),
                        'job_id' => $jobId,
                        'name' => $name,
                        'stage_type' => 'round',
                        'sort_order' => $maxOrder,
                        'is_system' => 0,
                        'is_active' => 1,
                    ]);
                    $maxOrder++;
                }
            }
        }
    }

    private function getTemplates($jobId)
    {
        $templates = db()->fetchAll('SELECT * FROM job_email_templates WHERE job_id = ?', [$jobId]);
        $result = ['invited' => '', 'rejected' => ''];
        foreach ($templates as $t) {
            $result[$t['template_type']] = $t['body_html'];
        }
        if (empty($result['invited'])) {
            $result['invited'] = $this->defaultInvitedTemplate();
        }
        if (empty($result['rejected'])) {
            $result['rejected'] = $this->defaultRejectedTemplate();
        }
        return $result;
    }

    private function saveTemplates($jobId, $post)
    {
        foreach (['invited', 'rejected'] as $type) {
            if (isset($post[$type])) {
                $existing = db()->fetch('SELECT id FROM job_email_templates WHERE job_id = ? AND template_type = ?', [$jobId, $type]);
                if ($existing) {
                    db()->update('job_email_templates', ['body_html' => $post[$type]], 'id = :id', ['id' => $existing['id']]);
                } else {
                    db()->insert('job_email_templates', [
                        'job_id' => $jobId,
                        'template_type' => $type,
                        'body_html' => $post[$type],
                    ]);
                }
            }
        }
    }

    private function defaultInvitedTemplate()
    {
        return "Dear [Candidate Name],\n\nCongratulations! You have been shortlisted for [Job Title] at [Company Name].\n\nWhat's Next?\n- Ensure stable internet connection\n- Interview duration: ~30 minutes\n\n[Start Your Interview Now]\n\nExpiry: [Expiry Date]\nContact: [Contact Email]\n\nRegards,\n[Hiring Manager Name]\n[Company Name]";
    }

    private function defaultRejectedTemplate()
    {
        return "Dear [Candidate Name],\n\nThank you for your interest in [Job Title] at [Company Name]. After careful review, we will not be moving forward at this time.\n\nWe wish you the best.\n\nRegards,\n[Company Name]";
    }
}
