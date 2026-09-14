<?php

class JobController
{
    private function masterData()
    {
        return [
            'departments' => db()->fetchAll('SELECT * FROM departments WHERE is_active = 1 ORDER BY name'),
            'seniority' => db()->fetchAll('SELECT * FROM seniority_levels ORDER BY sort_order'),
            'countries' => db()->fetchAll('SELECT * FROM countries ORDER BY name'),
            'work_modes' => db()->fetchAll('SELECT * FROM work_modes ORDER BY name'),
            'users' => db()->fetchAll('SELECT id, first_name, last_name, email, initials, role FROM users WHERE is_active = 1 ORDER BY first_name'),
        ];
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
        render('jobs.create', array_merge($this->masterData(), [
            'title' => 'Add New Job Role',
            'job' => null,
            'step' => 1,
            'activeNav' => 'jobs',
        ]));
    }

    public function storeStep1()
    {
        verify_csrf();
        $jobId = uuid();
        $skills = isset($_POST['key_skills']) ? array_filter(array_map('trim', explode(',', $_POST['key_skills']))) : [];

        db()->insert('jobs', [
            'id' => $jobId,
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
            'salary_period' => $_POST['salary_period'] ?: 'monthly',
            'hide_salary' => isset($_POST['hide_salary']) ? 1 : 0,
            'created_by' => auth_id(),
        ]);

        $this->syncManagers($jobId, $_POST);
        $this->createDefaultPipeline($jobId);
        $this->createDefaultRubrics($jobId, $skills);

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
        unset($_SESSION['wizard_job_id']);
        flash('success', 'Job role created successfully.');
        redirect(url('jobs/create/candidates?job_id=' . $jobId));
    }

    public function createCandidates()
    {
        $jobId = isset($_GET['job_id']) ? $_GET['job_id'] : '';
        redirect(url('candidates/create?job_id=' . $jobId));
    }

    public function edit($id)
    {
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        if (!$job) {
            http_response_code(404);
            die('Job not found');
        }
        $job['skills_str'] = implode(', ', json_decode($job['key_skills'], true) ?: []);
        $job['hiring_managers'] = db()->fetchAll('SELECT user_id FROM job_hiring_managers WHERE job_id = ?', [$id]);
        $job['interviewers'] = db()->fetchAll('SELECT user_id FROM job_interviewers WHERE job_id = ?', [$id]);
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
        $skills = isset($_POST['key_skills']) ? array_filter(array_map('trim', explode(',', $_POST['key_skills']))) : [];
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
            'salary_period' => $_POST['salary_period'] ?: 'monthly',
            'hide_salary' => isset($_POST['hide_salary']) ? 1 : 0,
        ], 'id = :id', ['id' => $id]);
        db()->query('DELETE FROM job_hiring_managers WHERE job_id = ?', [$id]);
        db()->query('DELETE FROM job_interviewers WHERE job_id = ?', [$id]);
        $this->syncManagers($id, $_POST);
        redirect(url('jobs/edit/' . $id . '/pipeline'));
    }

    public function editPipeline($id)
    {
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        $stages = db()->fetchAll('SELECT * FROM job_pipeline_stages WHERE job_id = ? ORDER BY sort_order', [$id]);
        render('jobs.pipeline', ['title' => 'Edit Pipeline', 'job' => $job, 'stages' => $stages, 'step' => 2, 'mode' => 'edit', 'activeNav' => 'jobs']);
    }

    public function updatePipeline($id)
    {
        verify_csrf();
        $this->savePipelineStages($id, $_POST);
        redirect(url('jobs/edit/' . $id . '/custom-question'));
    }

    public function editQuestions($id)
    {
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        $questions = db()->fetchAll('SELECT * FROM job_interview_questions WHERE job_id = ? ORDER BY sort_order', [$id]);
        render('jobs.questions', ['title' => 'Edit Interview Question', 'job' => $job, 'questions' => $questions, 'step' => 3, 'mode' => 'edit', 'activeNav' => 'jobs']);
    }

    public function updateQuestions($id)
    {
        verify_csrf();
        db()->query('DELETE FROM job_interview_questions WHERE job_id = ?', [$id]);
        if (!empty($_POST['questions'])) {
            foreach ($_POST['questions'] as $idx => $q) {
                $q = trim($q);
                if ($q) {
                    db()->insert('job_interview_questions', ['job_id' => $id, 'question_text' => $q, 'sort_order' => $idx]);
                }
            }
        }
        redirect(url('jobs/edit/' . $id . '/draft-message'));
    }

    public function editDraftMessage($id)
    {
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]);
        $templates = $this->getTemplates($id);
        render('jobs.draft_message', ['title' => 'Edit Draft Message', 'job' => $job, 'templates' => $templates, 'step' => 4, 'mode' => 'edit', 'activeNav' => 'jobs']);
    }

    public function updateDraftMessage($id)
    {
        verify_csrf();
        $this->saveTemplates($id, $_POST);
        flash('success', 'Job updated successfully.');
        redirect(url('jobs'));
    }

    public function show($id)
    {
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

    private function syncManagers($jobId, $post)
    {
        if (!empty($post['hiring_managers'])) {
            foreach ($post['hiring_managers'] as $uid) {
                db()->insert('job_hiring_managers', ['job_id' => $jobId, 'user_id' => $uid]);
            }
        }
        if (!empty($post['interviewers'])) {
            foreach ($post['interviewers'] as $uid) {
                db()->insert('job_interviewers', ['job_id' => $jobId, 'user_id' => $uid]);
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
            db()->insert('job_rubric_criteria', [
                'job_id' => $jobId,
                'name' => $name,
                'sort_order' => $idx,
            ]);
        }
    }

    private function savePipelineStages($jobId, $post)
    {
        if (empty($post['stages'])) {
            return;
        }
        db()->query('DELETE FROM job_pipeline_stages WHERE job_id = ? AND is_system = 0', [$jobId]);
        foreach ($post['stages'] as $idx => $stage) {
            if (empty($stage['name'])) {
                continue;
            }
            if (!empty($stage['id'])) {
                db()->update('job_pipeline_stages', [
                    'name' => $stage['name'],
                    'sort_order' => $idx,
                ], 'id = :id AND job_id = :jid', ['id' => $stage['id'], 'jid' => $jobId]);
            } else {
                db()->insert('job_pipeline_stages', [
                    'id' => uuid(),
                    'job_id' => $jobId,
                    'name' => $stage['name'],
                    'stage_type' => 'round',
                    'sort_order' => $idx,
                    'is_system' => 0,
                ]);
            }
        }
        if (!empty($post['new_stages'])) {
            foreach ($post['new_stages'] as $name) {
                $name = trim($name);
                if ($name) {
                    db()->insert('job_pipeline_stages', [
                        'id' => uuid(),
                        'job_id' => $jobId,
                        'name' => $name,
                        'stage_type' => 'round',
                        'sort_order' => 99,
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
