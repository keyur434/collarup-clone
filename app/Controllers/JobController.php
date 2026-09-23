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

        return [
            'departments' => db()->fetchAll('SELECT * FROM departments WHERE is_active = 1 ORDER BY name'),
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
            foreach (array_filter(array_map('trim', explode(',', $post['key_skills']))) as $s) {
                if ($s !== '' && !in_array($s, $skills, true)) {
                    $skills[] = $s;
                }
            }
        }
        return $skills;
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
        return $job;
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
            $where .= ' AND j.title LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $total = db()->fetch("SELECT COUNT(*) as c FROM jobs j WHERE $where", $params)['c'];
        $jobs = db()->fetchAll(
            "SELECT j.*, d.name as department_name, s.name as seniority_name,
                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) as applicants,
                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status IN ('completed','scheduled')) as candidates,
                    (SELECT COUNT(*) FROM interviews i JOIN applications a ON a.id = i.application_id WHERE a.job_id = j.id AND i.status = 'completed') as interviews_done
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN seniority_levels s ON s.id = j.seniority_id
             WHERE $where ORDER BY j.created_at DESC" . sql_page($perPage, $offset),
            $params
        );

        foreach ($jobs as &$job) {
            $job['managers'] = db()->fetchAll(
                'SELECT u.initials, u.first_name, u.last_name FROM job_hiring_managers jhm JOIN users u ON u.id = jhm.user_id WHERE jhm.job_id = ?',
                [$job['id']]
            );
        }

        render('jobs.index', [
            'title' => 'Job Roles',
            'jobs' => $jobs,
            'status' => $status,
            'search' => $search,
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

        $fields = [
            'title' => trim($_POST['title']),
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
        ];

        if ($exists) {
            db()->update('jobs', $fields, 'id = :id', ['id' => $jobId]);
            db()->query('DELETE FROM job_hiring_managers WHERE job_id = ?', [$jobId]);
            db()->query('DELETE FROM job_interviewers WHERE job_id = ?', [$jobId]);
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
        $stages = db()->fetchAll('SELECT * FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$jobId]);
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
        db()->update('jobs', [
            'title' => trim($_POST['title']),
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
        ], 'id = :id', ['id' => $id]);
        db()->query('DELETE FROM job_hiring_managers WHERE job_id = ?', [$id]);
        db()->query('DELETE FROM job_interviewers WHERE job_id = ?', [$id]);
        $this->syncManagers($id, $_POST);
        $this->syncExternalContacts($id, $_POST);
        redirect(url('jobs/edit/' . $id . '/pipeline'));
    }

    public function editPipeline($id)
    {
        $this->requireJobManage($id);
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        $stages = db()->fetchAll('SELECT * FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$id]);
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
                    ci.name as city_name, st.name as state_name, co.name as country_name
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN seniority_levels s ON s.id = j.seniority_id
             LEFT JOIN cities ci ON ci.id = j.city_id
             LEFT JOIN states st ON st.id = j.state_id
             LEFT JOIN countries co ON co.id = j.country_id
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
                 FROM applications a JOIN candidates c ON c.id = a.candidate_id WHERE a.job_id = ? ORDER BY a.application_date DESC",
                [$id]
            );
            render('jobs.show_applicant_pool', compact('job', 'applicants') + ['title' => $job['title'], 'activeNav' => 'jobs', 'tab' => $tab]);
            return;
        }

        $interviews = db()->fetchAll(
            "SELECT i.*, c.first_name, c.last_name, c.id as candidate_id,
                    a.decision,
                    ir.id as report_id, ir.overall_score, ir.score_label, ps.name as stage_name
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             LEFT JOIN interview_reports ir ON ir.interview_id = i.id
             LEFT JOIN job_pipeline_stages ps ON ps.id = i.stage_id
             WHERE a.job_id = ? ORDER BY i.created_at DESC",
            [$id]
        );

        render('jobs.show_interviews', [
            'title' => $job['title'],
            'job' => $job,
            'interviews' => $interviews,
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
        if (!empty($post['hiring_managers'])) {
            foreach ($post['hiring_managers'] as $uid) {
                if ($uid) {
                    db()->insert('job_hiring_managers', ['job_id' => $jobId, 'user_id' => $uid]);
                }
            }
        }
        if (!empty($post['interviewers'])) {
            foreach ($post['interviewers'] as $uid) {
                if ($uid) {
                    db()->insert('job_interviewers', ['job_id' => $jobId, 'user_id' => $uid]);
                }
            }
        }
    }

    private function syncExternalContacts($jobId, $post)
    {
        try {
            db()->query('DELETE FROM job_external_contacts WHERE job_id = ?', [$jobId]);
        } catch (Exception $e) {
            return;
        }
        $this->insertExternalContacts($jobId, 'hiring_manager', $post['external_hm_name'] ?? [], $post['external_hm_email'] ?? []);
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
            db()->insert('job_external_contacts', [
                'job_id' => $jobId,
                'contact_role' => $role,
                'name' => $name,
                'email' => $email ?: null,
            ]);
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
            db()->insert('job_rubric_criteria', [
                'job_id' => $jobId,
                'name' => $name,
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
            $rid = isset($ids[$idx]) ? (int) $ids[$idx] : 0;
            if ($rid > 0) {
                db()->update('job_rubric_criteria', [
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $idx,
                    'is_active' => 1,
                ], 'id = :id AND job_id = :jid', ['id' => $rid, 'jid' => $jobId]);
                $keptIds[] = $rid;
            } else {
                $newId = db()->insert('job_rubric_criteria', [
                    'job_id' => $jobId,
                    'name' => $name,
                    'description' => $description,
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
        if (empty($post['stages'])) {
            return;
        }
        $keptCustomIds = [];
        foreach ($post['stages'] as $idx => $stage) {
            if (empty($stage['name']) || empty($stage['id'])) {
                continue;
            }
            $row = db()->fetch('SELECT is_system FROM job_pipeline_stages WHERE id = ? AND job_id = ?', [$stage['id'], $jobId]);
            if (!$row) {
                continue;
            }
            $update = ['sort_order' => $idx];
            if (!(int) $row['is_system']) {
                $update['name'] = $stage['name'];
                $keptCustomIds[] = $stage['id'];
            }
            db()->update('job_pipeline_stages', $update, 'id = :id AND job_id = :jid', ['id' => $stage['id'], 'jid' => $jobId]);
        }
        if (!empty($keptCustomIds)) {
            $placeholders = implode(',', array_fill(0, count($keptCustomIds), '?'));
            db()->query(
                'DELETE FROM job_pipeline_stages WHERE job_id = ? AND is_system = 0 AND id NOT IN (' . $placeholders . ')',
                array_merge([$jobId], $keptCustomIds)
            );
        }
        if (!empty($post['new_stages'])) {
            $maxOrder = (int) db()->fetch('SELECT MAX(sort_order) as m FROM job_pipeline_stages WHERE job_id = ?', [$jobId])['m'];
            foreach ($post['new_stages'] as $name) {
                $name = trim($name);
                if ($name) {
                    $maxOrder++;
                    db()->insert('job_pipeline_stages', [
                        'id' => uuid(),
                        'job_id' => $jobId,
                        'name' => $name,
                        'stage_type' => 'round',
                        'sort_order' => $maxOrder,
                        'is_system' => 0,
                    ]);
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
