<?php

class CandidateController
{
    public function index()
    {
        $perPage = isset($_GET['perPage']) ? max(1, min(100, (int) $_GET['perPage'])) : 15;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($page - 1) * $perPage;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $where = '1=1';
        $params = [];
        if ($search) {
            $where .= ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if (!empty($_GET['department_id'])) {
            $where .= ' AND j.department_id = ?';
            $params[] = $_GET['department_id'];
        }
        if (!empty($_GET['job_id'])) {
            $where .= ' AND a.job_id = ?';
            $params[] = $_GET['job_id'];
        }
        if (!empty($_GET['status'])) {
            $where .= ' AND a.status = ?';
            $params[] = $_GET['status'];
        }
        if (empty($_GET['show_archived'])) {
            $where .= ' AND (a.is_archived = 0 OR a.is_archived IS NULL)';
        }
        list($accessSql, $accessParams) = PermissionService::applicationListAccessClause('j');
        $where .= $accessSql;
        $params = array_merge($params, $accessParams);

        $scope = isset($_GET['scope']) ? $_GET['scope'] : '';
        if ($scope === 'mine') {
            $where .= ' AND a.recruiter_id = ?';
            $params[] = auth_id();
        }

        if (!empty($_GET['recruiter_id']) && PermissionService::isAdminRole()) {
            $where .= ' AND a.recruiter_id = ?';
            $params[] = (int) $_GET['recruiter_id'];
        }

        $order = isset($_GET['order']) ? $_GET['order'] : 'created_desc';
        $orderSql = 'a.created_at DESC';
        if ($order === 'name') {
            $orderSql = 'c.last_name ASC, c.first_name ASC';
        } elseif ($order === 'recruiter') {
            $orderSql = 'recruiter.last_name ASC, recruiter.first_name ASC, a.created_at DESC';
        } elseif ($order === 'job') {
            $orderSql = 'j.title ASC, a.created_at DESC';
        }

        $total = db()->fetch(
            "SELECT COUNT(*) as c FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id WHERE $where",
            $params
        )['c'];

        $candidates = db()->fetchAll(
            "SELECT a.id as application_id, a.job_id, a.candidate_id, a.status, a.decision, a.application_date, a.created_at, a.is_archived,
                    a.recruiter_id,
                    c.first_name, c.last_name, c.email, c.resume_path, j.title as job_title, d.name as department_name,
                    recruiter.first_name AS recruiter_first_name, recruiter.last_name AS recruiter_last_name,
                    ps.name as stage_name,
                    (SELECT TOP 1 ir.overall_score FROM interviews i2
                     LEFT JOIN interview_reports ir ON ir.interview_id = i2.id
                     WHERE i2.application_id = a.id ORDER BY i2.created_at DESC) as overall_score,
                    (SELECT TOP 1 ir.score_label FROM interviews i2
                     LEFT JOIN interview_reports ir ON ir.interview_id = i2.id
                     WHERE i2.application_id = a.id ORDER BY i2.created_at DESC) as score_label,
                    (SELECT TOP 1 i2.scheduled_date FROM interviews i2 WHERE i2.application_id = a.id ORDER BY i2.created_at DESC) as scheduled_date,
                    (SELECT TOP 1 i2.scheduled_start FROM interviews i2 WHERE i2.application_id = a.id ORDER BY i2.created_at DESC) as scheduled_start,
                    (SELECT TOP 1 i2.scheduled_end FROM interviews i2 WHERE i2.application_id = a.id ORDER BY i2.created_at DESC) as scheduled_end,
                    (SELECT TOP 1 i2.id FROM interviews i2 WHERE i2.application_id = a.id ORDER BY i2.created_at DESC) as interview_id,
                    (SELECT TOP 1 ir.id FROM interviews i2
                     LEFT JOIN interview_reports ir ON ir.interview_id = i2.id
                     WHERE i2.application_id = a.id ORDER BY i2.created_at DESC) as report_id,
                    (SELECT COUNT(*) FROM applications a2
                     JOIN candidates c2 ON c2.id = a2.candidate_id
                     WHERE LOWER(c2.email) = LOWER(c.email)) as email_app_count
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN users recruiter ON recruiter.id = a.recruiter_id
             LEFT JOIN job_pipeline_stages ps ON ps.id = a.current_stage_id
             WHERE $where
             ORDER BY $orderSql" . sql_page($perPage, $offset),
            $params
        );

        $teamUsers = [];
        if (PermissionService::isAdminRole()) {
            $teamUsers = db()->fetchAll(
                'SELECT id, first_name, last_name FROM users WHERE is_active = 1 ORDER BY first_name, last_name'
            );
        }

        render('candidates.index', [
            'title' => 'Candidates',
            'candidates' => $candidates,
            'departments' => db()->fetchAll('SELECT * FROM departments ORDER BY name'),
            'jobs' => fetch_active_jobs_for_select(),
            'search' => $search,
            'scope' => $scope,
            'order' => $order,
            'teamUsers' => $teamUsers,
            'perPage' => $perPage,
            'page' => $page,
            'total' => $total,
            'totalPages' => max(1, ceil($total / $perPage)),
            'showArchived' => !empty($_GET['show_archived']),
            'sources' => db()->fetchAll('SELECT * FROM candidate_sources ORDER BY name'),
            'canBulkManage' => PermissionService::canManageCandidates(),
            'activeNav' => 'candidates',
        ]);
    }

    public function bulkAction()
    {
        verify_csrf();
        PermissionService::requireManageCandidates();

        $action = $_POST['action'] ?? '';
        $ids = isset($_POST['application_ids']) ? (array) $_POST['application_ids'] : [];
        $ids = array_filter(array_map('trim', $ids));

        if (empty($ids)) {
            flash('error', 'No candidates selected.');
            redirect(url('candidates'));
        }

        $count = 0;
        foreach ($ids as $applicationId) {
            $ctx = $this->applicationContext($applicationId);
            if (!PermissionService::canAccessJob($ctx['job_id'])) {
                continue;
            }

            if ($action === 'archive') {
                db()->update('applications', [
                    'is_archived' => 1,
                    'status' => 'cancelled',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $applicationId]);
                ActivityLogService::log('candidate_archive', 'application', $applicationId);
                $count++;
            } elseif ($action === 'restore' || $action === 'reactivate') {
                CandidateService::reactivate($applicationId);
                $count++;
            } elseif ($action === 'delete') {
                try {
                    CandidateService::deleteApplication($applicationId);
                    ActivityLogService::log('candidate_delete', 'application', $applicationId);
                    $count++;
                } catch (Exception $e) {
                    // skip row; bulk continues
                }
            }
        }

        flash('success', ucfirst($action) . " applied to $count candidate(s).");
        redirect(url('candidates' . (!empty($_POST['show_archived']) ? '?show_archived=1' : '')));
    }

    public function moveJob($applicationId)
    {
        verify_csrf();
        PermissionService::requireManageCandidates();
        $ctx = $this->applicationContext($applicationId);
        PermissionService::requireJobAccess($ctx['job_id']);

        $newJobId = $_POST['new_job_id'] ?? '';
        $stageId = $_POST['new_pipeline_id'] ?? '';
        $keepOnSourceJob = !empty($_POST['keep_on_source_job']);
        $archiveCurrent = !$keepOnSourceJob;

        if (!$newJobId || !$stageId) {
            flash('error', 'Select target job and pipeline stage.');
            redirect(url('applications/' . $applicationId . '/manage'));
        }

        try {
            $newAppId = CandidateService::moveToJob($applicationId, $newJobId, $stageId, $archiveCurrent);
            flash('success', 'Candidate moved to new job role.');
            redirect(url('applications/' . $newAppId . '/manage'));
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            redirect(url('applications/' . $applicationId . '/manage'));
        }
    }

    public function reactivate($applicationId)
    {
        verify_csrf();
        PermissionService::requireManageCandidates();
        $ctx = $this->applicationContext($applicationId);
        PermissionService::requireJobAccess($ctx['job_id']);

        CandidateService::reactivate($applicationId);
        flash('success', 'Candidate reactivated.');
        redirect(url('candidates'));
    }

    public function edit($applicationId)
    {
        $ctx = $this->applicationContext($applicationId);
        PermissionService::requireJobAccess($ctx['job_id']);

        $recruiters = PermissionService::isAdminRole()
            ? db()->fetchAll('SELECT id, first_name, last_name, email FROM users WHERE is_active = 1 ORDER BY first_name, last_name')
            : [];

        render('candidates.edit', [
            'title' => 'Manage Candidate',
            'app' => $ctx,
            'jobs' => fetch_active_jobs_for_select(),
            'recruiters' => $recruiters,
            'sources' => db()->fetchAll('SELECT * FROM candidate_sources ORDER BY name'),
            'stages' => db()->fetchAll(
                'SELECT id, name FROM job_pipeline_stages WHERE job_id = ? AND is_active = 1 ORDER BY sort_order',
                [$ctx['job_id']]
            ),
            'otherApplications' => CandidateService::otherApplications($ctx['candidate_id'], $applicationId),
            'emailAppCount' => CandidateService::emailApplicationCount($ctx['email']),
            'activity' => CandidateService::activityTimeline($ctx['candidate_id'], $applicationId),
            'canManage' => PermissionService::canManageCandidates(),
            'activeNav' => 'candidates',
        ]);
    }

    public function update($applicationId)
    {
        verify_csrf();
        $ctx = $this->applicationContext($applicationId);
        PermissionService::requireJobAccess($ctx['job_id']);
        PermissionService::requireManageCandidates();

        db()->update('candidates', [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? ''),
            'phone_country_code' => $_POST['phone_country_code'] ?: '+91',
            'years_experience' => $_POST['years_experience'] !== '' ? (float) $_POST['years_experience'] : null,
            'current_company' => trim($_POST['current_company'] ?? '') ?: null,
            'current_location' => trim($_POST['current_location'] ?? '') ?: null,
            'source_id' => $_POST['source_id'] ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $ctx['candidate_id']]);

        $appUpdate = [
            'current_stage_id' => $_POST['pipeline_id'] ?: $ctx['current_stage_id'],
            'status' => $_POST['status'] ?? $ctx['status'],
            'decision' => $_POST['decision'] ?? $ctx['decision'],
            'current_ctc' => trim($_POST['current_ctc'] ?? '') ?: null,
            'expected_ctc' => trim($_POST['expected_ctc'] ?? '') ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (PermissionService::isAdminRole() && isset($_POST['recruiter_id'])) {
            $rid = (int) $_POST['recruiter_id'];
            $appUpdate['recruiter_id'] = $rid > 0 ? $rid : null;
        }
        db()->update('applications', $appUpdate, 'id = :id', ['id' => $applicationId]);

        $resumeFailed = false;
        if (!empty($_FILES['resume']['name'])) {
            if ($_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
                flash('error', upload_error_message($_FILES['resume']['error']));
                $resumeFailed = true;
            } else {
                try {
                    $this->replaceResume($_FILES['resume'], $ctx['candidate_id'], $ctx['resume_path']);
                    ActivityLogService::log('resume_replace', 'candidate', $ctx['candidate_id']);
                } catch (RuntimeException $e) {
                    flash('error', $e->getMessage());
                    $resumeFailed = true;
                }
            }
        }

        ActivityLogService::log('candidate_update', 'application', $applicationId);
        flash('success', $resumeFailed ? 'Profile saved, but resume was not updated.' : 'Candidate updated.');
        redirect(url('applications/' . $applicationId . '/manage'));
    }

    public function archive($applicationId)
    {
        verify_csrf();
        PermissionService::requireManageCandidates();
        $ctx = $this->applicationContext($applicationId);
        PermissionService::requireJobAccess($ctx['job_id']);

        CandidateService::archiveApplication($applicationId);

        ActivityLogService::log('candidate_archive', 'application', $applicationId);
        flash('success', 'Candidate archived for this job.');
        redirect(url('candidates'));
    }

    public function restore($applicationId)
    {
        verify_csrf();
        PermissionService::requireManageCandidates();
        $ctx = $this->applicationContext($applicationId);
        PermissionService::requireJobAccess($ctx['job_id']);

        CandidateService::reactivate($applicationId);
        flash('success', 'Candidate restored.');
        redirect(url('applications/' . $applicationId . '/manage'));
    }

    public function destroy($applicationId)
    {
        verify_csrf();
        PermissionService::requireManageCandidates();
        $ctx = $this->applicationContext($applicationId);
        PermissionService::requireJobAccess($ctx['job_id']);

        try {
            CandidateService::deleteApplication($applicationId);
            ActivityLogService::log('candidate_delete', 'application', $applicationId);
            flash('success', 'Candidate removed from this job.');
        } catch (Exception $e) {
            flash('error', 'Could not remove application: ' . $e->getMessage());
            redirect(url('applications/' . $applicationId . '/manage'));
        }

        redirect(url('candidates'));
    }

    private function applicationContext($applicationId)
    {
        $row = db()->fetch(
            'SELECT a.*, c.first_name, c.last_name, c.email, c.phone, c.phone_country_code,
                    c.resume_path, c.years_experience, c.current_company, c.current_location, c.source_id,
                    j.title as job_title, j.created_by AS job_created_by,
                    recruiter.first_name AS recruiter_first_name, recruiter.last_name AS recruiter_last_name,
                    job_creator.first_name AS job_creator_first_name, job_creator.last_name AS job_creator_last_name
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN users recruiter ON recruiter.id = a.recruiter_id
             LEFT JOIN users job_creator ON job_creator.id = j.created_by
             WHERE a.id = ?',
            [$applicationId]
        );
        if (!$row) {
            http_response_code(404);
            die('Candidate not found.');
        }
        return $row;
    }

    private function replaceResume($file, $candidateId, $oldPath)
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = config('app')['allowed_resume_types'];
        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Invalid resume type. Allowed: ' . implode(', ', $allowed));
        }
        $maxBytes = (int) config('app')['resume_max_mb'] * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException('Resume exceeds ' . (int) config('app')['resume_max_mb'] . ' MB.');
        }

        $newName = $this->storeResume($file, $candidateId);

        if ($oldPath && $oldPath !== $newName) {
            $oldFile = BASE_PATH . '/storage/resumes/' . $oldPath;
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }
    }

    private function storeResume($file, $candidateId)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(upload_error_message($file['error']));
        }
        if (empty($file['tmp_name']) || (!is_uploaded_file($file['tmp_name']) && !is_readable($file['tmp_name']))) {
            throw new RuntimeException('No resume file received. ' . upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        $maxBytes = (int) config('app')['resume_max_mb'] * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException('Resume exceeds ' . (int) config('app')['resume_max_mb'] . ' MB.');
        }
        $serverLimit = php_upload_limit_bytes();
        if ($serverLimit > 0 && $file['size'] > $serverLimit) {
            throw new RuntimeException(upload_error_message(UPLOAD_ERR_INI_SIZE));
        }

        $dir = BASE_PATH . '/storage/resumes/';
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException('Could not create storage/resumes folder.');
            }
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('storage/resumes is not writable by the web server.');
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $name = $candidateId . '_' . $safeBase;
        $dest = $dir . $name;

        $moved = false;
        if (is_uploaded_file($file['tmp_name'])) {
            $moved = move_uploaded_file($file['tmp_name'], $dest);
        } elseif (is_readable($file['tmp_name'])) {
            $moved = copy($file['tmp_name'], $dest);
        }
        if (!$moved) {
            throw new RuntimeException('Could not save resume file to storage/resumes.');
        }

        db()->update('candidates', [
            'resume_path' => $name,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $candidateId]);

        return $name;
    }

    public function create()
    {
        $jobId = isset($_GET['job_id']) ? $_GET['job_id'] : '';
        $pipelineId = isset($_GET['pipeline_id']) ? $_GET['pipeline_id'] : '';
        render('candidates.create', [
            'title' => 'Add Candidate',
            'jobs' => fetch_active_jobs_for_select(),
            'sources' => db()->fetchAll('SELECT * FROM candidate_sources ORDER BY name'),
            'selectedJobId' => $jobId,
            'selectedPipelineId' => $pipelineId,
            'activeNav' => 'candidates',
        ]);
    }

    public function storeFromWizard($jobId)
    {
        $redirect = url('jobs/' . $jobId . '/interviews');
        $this->storeInternal($redirect);
    }

    public function store()
    {
        verify_csrf();
        $this->storeInternal(url('candidates'));
    }

    public function previewBulkImport()
    {
        verify_csrf();
        $jobId = trim($_POST['job_id'] ?? '');
        if ($jobId === '') {
            json_response(['error' => 'Select a job role first.'], 400);
        }
        if (empty($_FILES['resumes'])) {
            json_response(['error' => 'No resume files uploaded.'], 400);
        }

        $splitCompiled = !empty($_POST['split_compiled_pdf']);
        $expanded = BulkResumeImportService::expandFiles($_FILES['resumes'], $splitCompiled);
        if ($expanded === []) {
            json_response(['error' => 'No valid files to import.'], 400);
        }

        $items = [];
        foreach ($expanded as $i => $file) {
            $items[] = $this->bulkPreviewItem($file, $i, $jobId);
        }

        json_response([
            'items' => $items,
            'source_file_count' => $this->countUploadedFiles($_FILES['resumes']),
            'candidate_count' => count($items),
            'split_applied' => count($items) > $this->countUploadedFiles($_FILES['resumes']),
        ]);
    }

    public function parseResume()
    {
        verify_csrf();
        $file = null;
        if (!empty($_FILES['resume']['name']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['resume'];
        } elseif (!empty($_FILES['resumes']['name'])) {
            $names = $_FILES['resumes']['name'];
            if (is_array($names)) {
                foreach ($names as $i => $name) {
                    if ($name && $_FILES['resumes']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['resumes']['name'][$i],
                            'tmp_name' => $_FILES['resumes']['tmp_name'][$i],
                            'error' => $_FILES['resumes']['error'][$i],
                            'size' => $_FILES['resumes']['size'][$i],
                        ];
                        break;
                    }
                }
            } else {
                $file = $_FILES['resumes'];
            }
        }
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            json_response(['error' => 'No resume file received.'], 400);
        }
        $parsed = $this->parseResumeFile($file);
        json_response(['parsed' => $parsed, 'filename' => $file['name']]);
    }

    private function storeInternal($redirectAfter)
    {
        $tab = isset($_POST['tab']) ? $_POST['tab'] : 'individual';
        if (!empty($_FILES['csv']['name'])) {
            $tab = 'bulk';
        }
        $jobId = $_POST['job_id'];
        $stageId = $_POST['pipeline_id'];

        if ($tab === 'bulk') {
            $this->handleBulkImport($jobId, $stageId, $redirectAfter);
            return;
        }

        if ($tab === 'resume') {
            $resumeCount = $this->countUploadedFiles($_FILES['resumes'] ?? null);
            if ($resumeCount > 0) {
                $email = trim($_POST['email'] ?? '');
                $multiInOnePdf = false;
                if ($resumeCount === 1 && !empty($_FILES['resumes']['tmp_name'])) {
                    $tmp = is_array($_FILES['resumes']['tmp_name'])
                        ? $_FILES['resumes']['tmp_name'][0]
                        : $_FILES['resumes']['tmp_name'];
                    $uploadName = is_array($_FILES['resumes']['name']) ? $_FILES['resumes']['name'][0] : $_FILES['resumes']['name'];
                    if (is_readable($tmp) && strtolower(pathinfo($uploadName, PATHINFO_EXTENSION)) === 'pdf') {
                        $multiInOnePdf = BulkResumeImportService::pdfLikelyContainsMultipleCvs($tmp);
                    }
                }
                if ($resumeCount === 1 && $email !== '' && !$multiInOnePdf) {
                    $this->handleSingleResumeWithCard($jobId, $stageId, $redirectAfter);
                    return;
                }
                if (!empty($_POST['bulk_preview_confirmed']) && !empty($_POST['bulk_items'])) {
                    $this->handleResumeUploadFromPreview($jobId, $stageId, $redirectAfter);
                    return;
                }
                flash('error', 'Use “Preview import” first, review or fix each row, then “Add candidates”.');
                redirect(url('candidates/create?job_id=' . urlencode($jobId)));
            }
        }

        $parsed = [];
        if (!empty($_FILES['resume']['name']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $parsed = $this->parseResumeFile($_FILES['resume']);
        }
        $row = $this->candidateRowFromRequest($_POST, $parsed, $_FILES['resume']['name'] ?? '');
        $email = $row['email'];
        if ($email === '') {
            flash('error', 'Email is required.');
            redirect(url('candidates/create?job_id=' . $jobId));
        }

        $existing = CandidateService::findByEmail($email);

        if ($existing) {
            $onJob = CandidateService::applicationOnJob($existing['id'], $jobId);
            if ($onJob) {
                flash('error', 'This email is already on this job.');
                redirect(url('applications/' . $onJob['id'] . '/manage'));
            }
            db()->update('candidates', [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone_country_code' => $row['phone_country_code'],
                'phone' => $row['phone'],
                'years_experience' => $row['years_experience'],
                'current_company' => $row['current_company'],
                'source_id' => $row['source_id'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $existing['id']]);
            if (!empty($_FILES['resume']['name'])) {
                try {
                    $this->replaceResume($_FILES['resume'], $existing['id'], $existing['resume_path']);
                    (new ResumeParserService())->applyToCandidate($existing['id'], $parsed);
                } catch (RuntimeException $e) {
                    flash('error', $e->getMessage());
                    redirect(url('candidates/create?job_id=' . $jobId));
                }
            }
            $this->createApplication($existing['id'], $jobId, $stageId);
            ActivityLogService::log('candidate_reuse', 'candidate', $existing['id'], ['job_id' => $jobId]);
            flash('success', 'Existing candidate added to this job role.');
            redirect($redirectAfter);
        }

        $candidateId = uuid();
        db()->insert('candidates', [
            'id' => $candidateId,
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $email,
            'phone_country_code' => $row['phone_country_code'],
            'phone' => $row['phone'],
            'years_experience' => $row['years_experience'],
            'current_company' => $row['current_company'],
            'source_id' => $row['source_id'],
        ]);

        if (!empty($_FILES['resume']['name'])) {
            try {
                $this->storeResume($_FILES['resume'], $candidateId);
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect(url('candidates/create?job_id=' . $jobId));
            }
        }

        $this->createApplication($candidateId, $jobId, $stageId);
        ActivityLogService::log('candidate_create', 'candidate', $candidateId);
        flash('success', 'Candidate added successfully.');
        redirect($redirectAfter);
    }

    public function checkEmail()
    {
        $email = trim($_GET['email'] ?? '');
        $jobId = $_GET['job_id'] ?? '';
        if (!$email) {
            json_response(['duplicate' => false]);
        }

        $existing = CandidateService::findByEmail($email);
        if (!$existing) {
            json_response(['duplicate' => false]);
        }

        $onJob = $jobId ? CandidateService::applicationOnJob($existing['id'], $jobId) : null;
        json_response([
            'duplicate' => true,
            'candidate_id' => $existing['id'],
            'name' => $existing['first_name'] . ' ' . $existing['last_name'],
            'on_job' => !empty($onJob),
            'application_id' => $onJob ? $onJob['id'] : null,
            'other_jobs' => count(CandidateService::otherApplications($existing['id'])),
        ]);
    }

    public function details($id)
    {
        $interview = db()->fetch(
            "SELECT i.*, a.job_id, a.candidate_id, a.decision,
                    c.first_name, c.last_name, c.email, c.phone, c.phone_country_code,
                    j.title as job_title, d.name as department_name, ps.name as stage_name
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN job_pipeline_stages ps ON ps.id = i.stage_id
             WHERE i.id = ?",
            [$id]
        );
        if (!$interview) {
            json_response(['error' => 'Not found'], 404);
        }
        PermissionService::requireJobAccess($interview['job_id']);
        $user = auth_user();
        if ($user && !empty($user['email'])) {
            $interview['default_organizer_email'] = $user['email'];
        }
        $interview['scheduled_start'] = normalize_time_input($interview['scheduled_start'] ?? '');
        $interview['scheduled_end'] = normalize_time_input($interview['scheduled_end'] ?? '');
        json_response(['interview' => $interview]);
    }

    public function requeueAnalysis($id)
    {
        verify_csrf();
        $interview = db()->fetch(
            'SELECT i.*, a.job_id FROM interviews i JOIN applications a ON a.id = i.application_id WHERE i.id = ?',
            [$id]
        );
        if (!$interview) {
            flash('error', 'Interview not found.');
            redirect(url('candidates'));
        }
        PermissionService::requireJobAccess($interview['job_id']);
        $redirect = url('jobs/' . $interview['job_id'] . '/interviews');

        try {
            InterviewWorkflowService::requeueAnalyze($id);
            flash('success', 'Analysis queued. Ensure queue worker is running (cron/queue_worker.php).');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
        redirect($redirect);
    }

    public function fetchRecordingNow($id)
    {
        verify_csrf();
        $interview = db()->fetch(
            'SELECT i.*, a.job_id FROM interviews i JOIN applications a ON a.id = i.application_id WHERE i.id = ?',
            [$id]
        );
        if (!$interview) {
            flash('error', 'Interview not found.');
            redirect(url('candidates'));
        }
        PermissionService::requireJobAccess($interview['job_id']);
        $redirect = url('jobs/' . $interview['job_id'] . '/interviews');

        try {
            $teams = new TeamsRecordingService();
            if ($teams->forceQueueRecordingFetch($id)) {
                flash('success', 'Recording fetch queued. Run queue worker or wait ~1 minute.');
            } else {
                flash('error', 'Could not queue fetch — check Teams link, organizer email, and Graph config.');
            }
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
        redirect($redirect);
    }

    public function registerTeamsLink($id)
    {
        verify_csrf();
        $interview = db()->fetch('SELECT * FROM interviews WHERE id = ?', [$id]);
        if (!$interview) {
            flash('error', 'Interview not found.');
            redirect(url('candidates'));
        }

        $app = db()->fetch('SELECT * FROM applications WHERE id = ?', [$interview['application_id']]);
        if (!$app) {
            flash('error', 'Application not found.');
            redirect(url('candidates'));
        }
        PermissionService::requireJobAccess($app['job_id']);
        $redirect = url('jobs/' . $app['job_id'] . '/interviews');

        $meetingUrl = trim($_POST['meeting_url'] ?? '');
        $scheduledDate = $_POST['scheduled_date'] ?? '';
        $organizerEmail = trim($_POST['organizer_email'] ?? '');

        if ($meetingUrl === '' || $scheduledDate === '') {
            flash('error', 'Teams join link and interview date are required.');
            redirect($redirect);
        }

        if (!$this->isTeamsJoinUrl($meetingUrl)) {
            flash('error', 'Paste the Teams join link from Keka (not the recording recap link).');
            redirect($redirect);
        }

        list($scheduledStart, $scheduledEnd) = $this->scheduleTimesFromPost($_POST);

        db()->update('interviews', [
            'scheduled_date' => $scheduledDate,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'status' => 'scheduled',
            'meeting_url' => $meetingUrl,
        ], 'id = :id', ['id' => $id]);

        db()->update('applications', [
            'status' => 'scheduled',
        ], 'id = :id', ['id' => $interview['application_id']]);

        try {
            $teams = new TeamsRecordingService();
            $teams->registerManualTeamsLink(
                $id,
                $meetingUrl,
                $scheduledDate,
                $scheduledEnd,
                $organizerEmail !== '' ? $organizerEmail : null
            );
            $teams->queueRecordingFetchIfDue($id);
        } catch (Exception $e) {
            flash('error', 'Date and link saved, but Teams was not registered for recording: ' . $e->getMessage() . ' Check host email and Graph config, then save again.');
            redirect($redirect);
        }

        flash('success', 'Teams link saved. Recording will be picked up automatically after the interview.');
        redirect($redirect);
    }

    public function updateDetails($id)
    {
        verify_csrf();
        $interview = db()->fetch(
            'SELECT i.*, a.job_id FROM interviews i JOIN applications a ON a.id = i.application_id WHERE i.id = ?',
            [$id]
        );
        if (!$interview) {
            flash('error', 'Interview not found.');
            redirect(url('candidates'));
        }
        PermissionService::requireJobAccess($interview['job_id']);

        $scheduledDate = $_POST['scheduled_date'] ?: null;
        $manualMeetingUrl = trim($_POST['meeting_url'] ?? '');
        list($scheduledStart, $scheduledEnd) = $this->scheduleTimesFromPost($_POST);
        $organizerEmail = trim($_POST['organizer_email'] ?? '');
        $interviewUpdate = [
            'stage_id' => $_POST['stage_id'],
            'scheduled_date' => $scheduledDate,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'status' => $scheduledDate ? 'scheduled' : 'pending',
        ];
        if ($manualMeetingUrl !== '') {
            $interviewUpdate['meeting_url'] = $manualMeetingUrl;
        }
        db()->update('interviews', $interviewUpdate, 'id = :id', ['id' => $id]);

        $app = db()->fetch('SELECT * FROM applications WHERE id = ?', [$interview['application_id']]);
        db()->update('candidates', [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone' => $_POST['phone'],
            'phone_country_code' => $_POST['phone_country_code'] ?: '+91',
        ], 'id = :id', ['id' => $app['candidate_id']]);

        db()->update('applications', [
            'current_stage_id' => $_POST['stage_id'],
            'status' => $_POST['scheduled_date'] ? 'scheduled' : 'pending',
        ], 'id = :id', ['id' => $interview['application_id']]);

        if ($scheduledDate) {
            $teams = new TeamsRecordingService();
            $teamsMsg = '';
            $teamsError = null;
            $teamsRecording = false;
            try {
                if ($manualMeetingUrl === '' && $teams->autoCreateEnabled() && empty($interview['teams_meeting_id'])) {
                    $teams->createMeetingForInterview($id);
                    $teamsMsg = ' Teams meeting created under your account.';
                    $teamsRecording = true;
                } elseif ($manualMeetingUrl === '' && !empty($interview['teams_meeting_id'])) {
                    $teams->ensureRecordingPolling($id, $scheduledDate, $scheduledEnd, $scheduledStart);
                    $teamsRecording = true;
                } elseif ($manualMeetingUrl !== '' && $this->detectMeetingType($manualMeetingUrl) === 'teams') {
                    if (!$this->isTeamsJoinUrl($manualMeetingUrl)) {
                        $teamsError = 'Use the Teams join link from the invite, not a meeting recap / recording URL.';
                    } elseif (!$teams->graphReady()) {
                        $teamsError = 'Microsoft Graph is not configured — Teams recording cannot be registered.';
                    } else {
                        $teams->registerManualTeamsLink(
                            $id,
                            $manualMeetingUrl,
                            $scheduledDate,
                            $scheduledEnd,
                            $organizerEmail !== '' ? $organizerEmail : null
                        );
                        $teams->queueRecordingFetchIfDue($id);
                        $teamsRecording = true;
                    }
                }
            } catch (Exception $e) {
                $teamsError = $e->getMessage();
            }

            if (!empty($_POST['send_invite_email'])) {
                try {
                    $notify = new NotificationService();
                    $notify->notifyInterviewScheduled($id, true);
                } catch (Exception $e) {
                    // non-fatal
                }
            }

            if ($teamsError) {
                flash('error', 'Interview saved, but Teams recording setup failed: ' . $teamsError);
            } elseif ($teamsRecording) {
                flash('success', 'Interview scheduled.' . $teamsMsg . ' Recording will be captured automatically after the call.');
            } else {
                flash('success', 'Interview scheduled.');
            }
            redirect(url('jobs/' . $app['job_id'] . '/interviews'));
        }

        flash('success', 'Interview details saved.');
        redirect(url('jobs/' . $app['job_id'] . '/interviews'));
    }

    public function downloadResumeByApplication($applicationId)
    {
        $row = db()->fetch(
            'SELECT c.id as candidate_id, c.resume_path, c.first_name, c.last_name, a.job_id
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             WHERE a.id = ?',
            [$applicationId]
        );
        if (!$row || empty($row['resume_path'])) {
            http_response_code(404);
            die('Resume not found in database. Upload again from Manage.');
        }
        $this->serveResumeFile($row, !empty($_GET['preview']));
    }

    public function downloadResume($candidateId)
    {
        $applicationId = isset($_GET['application_id']) ? $_GET['application_id'] : null;
        if ($applicationId) {
            return $this->downloadResumeByApplication($applicationId);
        }

        $row = db()->fetch(
            'SELECT TOP 1 c.id as candidate_id, c.resume_path, c.first_name, c.last_name, a.job_id
             FROM candidates c
             JOIN applications a ON a.candidate_id = c.id
             WHERE c.id = ?
             ORDER BY a.created_at DESC',
            [$candidateId]
        );
        if (!$row || empty($row['resume_path'])) {
            http_response_code(404);
            die('Resume not found.');
        }
        $this->serveResumeFile($row, !empty($_GET['preview']));
    }

    private function serveResumeFile($row, $preview)
    {
        PermissionService::requireJobAccess($row['job_id']);

        $path = BASE_PATH . '/storage/resumes/' . $row['resume_path'];
        if (!file_exists($path)) {
            db()->update('candidates', [
                'resume_path' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $row['candidate_id']]);
            http_response_code(404);
            $limit = php_upload_limit_mb();
            $hint = $limit > 0 ? ' Server PHP upload limit is ' . $limit . ' MB — raise upload_max_filesize in php.ini if uploads fail.' : '';
            die('Resume file missing on disk (record cleared). Upload again from Manage.' . $hint);
        }

        ActivityLogService::log($preview ? 'resume_preview' : 'resume_download', 'candidate', $row['candidate_id']);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['first_name'] . '_' . $row['last_name']) . '.' . $ext;

        $mimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

        header('Content-Type: ' . $mime);
        if ($preview && $ext === 'pdf') {
            header('Content-Disposition: inline; filename="' . $name . '"');
            header('X-Frame-Options: SAMEORIGIN');
            header('Cache-Control: private, max-age=300');
        } else {
            header('Content-Disposition: attachment; filename="' . $name . '"');
        }
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function uploadVideo($id)
    {
        verify_csrf();
        $interview = InterviewWorkflowService::interviewContext($id);
        if (!$interview) {
            flash('error', 'Interview not found.');
            redirect(url('candidates'));
        }
        PermissionService::requireJobAccess($interview['job_id']);

        $redirect = safe_app_redirect(
            isset($_POST['redirect_to']) ? $_POST['redirect_to'] : '',
            InterviewWorkflowService::redirectUrl($id)
        );
        $hasVideo = !empty($_FILES['video']['name']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE;
        $meetingUrl = trim($_POST['meeting_url'] ?? '');
        $hasUrl = $meetingUrl !== '';

        if (!$hasVideo && !$hasUrl) {
            if (request_content_length() > 1024 && empty($_FILES)) {
                flash('error', 'Video upload was not received. Server limits: post_max_size=' . ini_get('post_max_size') . ', upload_max_filesize=' . ini_get('upload_max_filesize') . '.');
            } else {
                flash('error', 'Upload a video file or paste a meeting / recording link.');
            }
            redirect($redirect);
        }

        $tempDir = BASE_PATH . '/storage/temp/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        if ($hasUrl) {
            db()->update('interviews', ['meeting_url' => $meetingUrl], 'id = :id', ['id' => $id]);
        }

        if ($hasVideo) {
            if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                flash('error', upload_error_message($_FILES['video']['error']));
                redirect($redirect);
            }

            $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
            $allowed = config('app')['allowed_video_types'];
            if (!in_array($ext, $allowed, true)) {
                flash('error', 'Invalid video type. Allowed: ' . implode(', ', $allowed));
                redirect($redirect);
            }

            $maxBytes = (int) config('app')['upload_max_mb'] * 1024 * 1024;
            if ($_FILES['video']['size'] > $maxBytes) {
                flash('error', 'Video exceeds ' . config('app')['upload_max_mb'] . ' MB limit.');
                redirect($redirect);
            }

            $localPath = $tempDir . $id . '_upload.' . $ext;
            if (!move_uploaded_file($_FILES['video']['tmp_name'], $localPath)) {
                flash('error', 'Could not save uploaded video.');
                redirect($redirect);
            }

            $mediaId = db()->insert('interview_media', [
                'interview_id' => $id,
                'media_type' => 'upload',
                'original_filename' => $_FILES['video']['name'],
                'blob_container' => 'pending',
                'blob_name' => $id . '_pending.' . $ext,
                'playback_mode' => 'blob',
            ]);

            InterviewWorkflowService::queueMediaProcessing($id, $localPath, $mediaId);
            flash('success', 'Video uploaded. Processing started — run the queue worker to generate the report.');
            redirect($redirect);
        }

        // Meeting / recording URL only (no file upload)
        if (InterviewWorkflowService::isDirectVideoUrl($meetingUrl)) {
            try {
                $localPath = $tempDir . $id . '_remote.mp4';
                InterviewWorkflowService::downloadRemoteVideo($meetingUrl, $localPath);
                $mediaId = db()->insert('interview_media', [
                    'interview_id' => $id,
                    'media_type' => 'external',
                    'external_url' => $meetingUrl,
                    'original_filename' => basename(parse_url($meetingUrl, PHP_URL_PATH)),
                    'blob_container' => 'pending',
                    'blob_name' => $id . '_pending.mp4',
                    'playback_mode' => 'blob',
                ]);
                InterviewWorkflowService::queueMediaProcessing($id, $localPath, $mediaId);
                flash('success', 'Recording link saved and queued for AI analysis.');
            } catch (Exception $e) {
                db()->insert('interview_media', [
                    'interview_id' => $id,
                    'media_type' => $this->detectMeetingType($meetingUrl),
                    'external_url' => $meetingUrl,
                    'playback_mode' => 'embed',
                ]);
                flash('success', 'Meeting link saved. Upload the interview video file to run AI analysis.');
            }
        } else {
            $mediaId = db()->insert('interview_media', [
                'interview_id' => $id,
                'media_type' => $this->detectMeetingType($meetingUrl),
                'external_url' => $meetingUrl,
                'playback_mode' => 'embed',
                'processing_status' => 'pending',
            ]);

            if ($this->detectMeetingType($meetingUrl) === 'teams' && $this->isTeamsJoinUrl($meetingUrl)) {
                $teams = new TeamsRecordingService();
                if ($teams->graphReady()) {
                    try {
                        $row = db()->fetch('SELECT scheduled_date, scheduled_start, scheduled_end FROM interviews WHERE id = ?', [$id]);
                        $org = trim($_POST['organizer_email'] ?? '') ?: null;
                        $teams->registerManualTeamsLink(
                            $id,
                            $meetingUrl,
                            $row['scheduled_date'] ?? date('Y-m-d'),
                            $row['scheduled_end'] ?? null,
                            $org
                        );
                        $teams->queueRecordingFetchIfDue($id);
                        flash('success', 'Teams link registered. Fetching recording from Microsoft when ready...');
                    } catch (Exception $e) {
                        flash('error', 'Link saved but Teams registration failed: ' . $e->getMessage());
                    }
                } else {
                    flash('success', 'Meeting link saved. Configure Graph sync app to auto-fetch Teams recordings.');
                }
            } else {
                flash('success', 'Meeting link saved. Upload the recorded interview video to run AI analysis.');
            }
        }

        redirect($redirect);
    }

    private function createApplication($candidateId, $jobId, $stageId, array $applicationExtras = [])
    {
        $appId = uuid();
        $recruiterId = auth_id();
        $ctc = $this->normalizeApplicationCtc($applicationExtras !== [] ? $applicationExtras : $_POST);
        db()->insert('applications', array_merge([
            'id' => $appId,
            'candidate_id' => $candidateId,
            'job_id' => $jobId,
            'current_stage_id' => $stageId,
            'status' => 'pending',
            'application_date' => date('Y-m-d'),
            'recruiter_id' => $recruiterId ?: null,
        ], $ctc));

        $interviewId = uuid();
        db()->insert('interviews', [
            'id' => $interviewId,
            'application_id' => $appId,
            'stage_id' => $stageId,
            'status' => 'pending',
        ]);
    }

    private function parseResumeFile(array $file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK || !is_readable($file['tmp_name'])) {
            return [];
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return (new ResumeParserService())->parseFile($file['tmp_name'], $ext);
    }

    private function countUploadedFiles($files)
    {
        if (empty($files) || empty($files['name'])) {
            return 0;
        }
        if (!is_array($files['name'])) {
            return $files['error'] === UPLOAD_ERR_NO_FILE ? 0 : 1;
        }
        $n = 0;
        foreach ($files['name'] as $i => $name) {
            if ($name && ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $n++;
            }
        }
        return $n;
    }

    private function candidateRowFromRequest(array $post, array $parsed, $filename = '')
    {
        $first = trim($post['first_name'] ?? '');
        $last = trim($post['last_name'] ?? '');
        $email = trim($post['email'] ?? '');
        if ($first === '' && !empty($parsed['first_name'])) {
            $first = $parsed['first_name'];
        }
        if ($last === '' && !empty($parsed['last_name'])) {
            $last = $parsed['last_name'];
        }
        if ($email === '' && !empty($parsed['email'])) {
            $email = $parsed['email'];
        }
        $phone = trim($post['phone'] ?? '');
        if ($phone === '' && !empty($parsed['phone'])) {
            $phone = $parsed['phone'];
        }
        $years = $post['years_experience'] ?? '';
        $yearsExp = $years !== '' ? (float) $years : null;
        if ($yearsExp === null && isset($parsed['years_experience'])) {
            $yearsExp = (float) $parsed['years_experience'];
        }
        $company = trim($post['current_company'] ?? '');
        if ($company === '' && !empty($parsed['current_company'])) {
            $company = $parsed['current_company'];
        }

        return [
            'first_name' => $first !== '' ? $first : 'Candidate',
            'last_name' => $last,
            'email' => $email,
            'phone_country_code' => $post['phone_country_code'] ?: '+91',
            'phone' => $phone,
            'years_experience' => $yearsExp,
            'current_company' => $company !== '' ? $company : null,
            'source_id' => $this->resolveSourceId($post, $filename) ?: null,
        ];
    }

    private function handleSingleResumeWithCard($jobId, $stageId, $redirectAfter)
    {
        $files = $_FILES['resumes'];
        $file = [
            'name' => is_array($files['name']) ? $files['name'][0] : $files['name'],
            'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][0] : $files['tmp_name'],
            'error' => is_array($files['error']) ? $files['error'][0] : $files['error'],
            'size' => is_array($files['size']) ? $files['size'][0] : $files['size'],
        ];
        $parsed = $this->parseResumeFile($file);
        $row = $this->candidateRowFromRequest($_POST, $parsed, $file['name']);
        if ($row['email'] === '') {
            flash('error', 'Email is required. Check parsed details or enter manually.');
            redirect(url('candidates/create?job_id=' . $jobId));
        }

        $existing = CandidateService::findByEmail($row['email']);
        if ($existing && CandidateService::applicationOnJob($existing['id'], $jobId)) {
            flash('error', 'This email is already on this job.');
            redirect(url('applications/' . CandidateService::applicationOnJob($existing['id'], $jobId)['id'] . '/manage'));
        }

        if ($existing) {
            db()->update('candidates', [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone_country_code' => $row['phone_country_code'],
                'phone' => $row['phone'],
                'years_experience' => $row['years_experience'],
                'current_company' => $row['current_company'],
                'source_id' => $row['source_id'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $existing['id']]);
            $candidateId = $existing['id'];
        } else {
            $candidateId = uuid();
            db()->insert('candidates', [
                'id' => $candidateId,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone_country_code' => $row['phone_country_code'],
                'phone' => $row['phone'],
                'years_experience' => $row['years_experience'],
                'current_company' => $row['current_company'],
                'source_id' => $row['source_id'],
            ]);
        }

        try {
            $this->storeResume($file, $candidateId);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(url('candidates/create?job_id=' . $jobId));
        }

        if (!$existing || !CandidateService::applicationOnJob($candidateId, $jobId)) {
            $this->createApplication($candidateId, $jobId, $stageId);
        }
        flash('success', 'Candidate added from resume.');
        redirect($redirectAfter);
    }

    private function normalizeApplicationCtc(array $data)
    {
        $current = trim((string) ($data['current_ctc'] ?? ''));
        $expected = trim((string) ($data['expected_ctc'] ?? ''));

        return [
            'current_ctc' => $current !== '' ? $current : null,
            'expected_ctc' => $expected !== '' ? $expected : null,
        ];
    }

    private function resolveSourceId($post, $filename = '')
    {
        if (!empty($post['source_id'])) {
            return $post['source_id'];
        }
        if (stripos($filename, 'naukri') !== false) {
            $row = db()->fetch("SELECT id FROM candidate_sources WHERE name = N'Naukri'");
            if ($row) {
                return $row['id'];
            }
        }
        return null;
    }

    private function handleResumeUploadFromPreview($jobId, $stageId, $redirectAfter = null)
    {
        $redirectAfter = $redirectAfter ?: url('candidates');
        if (empty($_FILES['resumes'])) {
            flash('error', 'Files missing — run Preview import again with the same files attached.');
            redirect(url('candidates/create?job_id=' . $jobId));
        }

        $rows = json_decode($_POST['bulk_items'] ?? '', true);
        if (!is_array($rows)) {
            flash('error', 'Invalid preview data. Run Preview import again.');
            redirect(url('candidates/create?job_id=' . $jobId));
        }

        $splitCompiled = !empty($_POST['split_compiled_pdf']);
        $expanded = BulkResumeImportService::expandFiles($_FILES['resumes'], $splitCompiled);
        $stats = ['added' => 0, 'skipped' => 0, 'excluded' => 0];

        foreach ($rows as $rowIn) {
            if (empty($rowIn['include'])) {
                $stats['excluded']++;
                continue;
            }
            $idx = (int) ($rowIn['file_index'] ?? -1);
            if (!isset($expanded[$idx])) {
                continue;
            }
            $file = $expanded[$idx];
            $row = $this->bulkRowFromPreviewPost($rowIn);
            $result = $this->importOneBulkResume($jobId, $stageId, $file, $row, $idx);
            if ($result === 'skipped') {
                $stats['skipped']++;
            } elseif ($result === 'added') {
                $stats['added']++;
            }
        }

        $src = $this->countUploadedFiles($_FILES['resumes']);
        $msg = $stats['added'] . ' candidate(s) added from ' . $src . ' uploaded file(s).';
        if ($stats['skipped']) {
            $msg .= ' ' . $stats['skipped'] . ' skipped (already on this job).';
        }
        if ($stats['excluded']) {
            $msg .= ' ' . $stats['excluded'] . ' excluded in preview.';
        }
        flash('success', $msg);
        redirect($redirectAfter);
    }

    private function bulkPreviewItem(array $file, $fileIndex, $jobId)
    {
        if (!empty($file['parsed_text'])) {
            $parsed = (new ResumeParserService())->parseText($file['parsed_text']);
        } else {
            $parsed = $this->parseResumeFile($file);
        }
        $row = $this->candidateRowFromRequest([], $parsed, $file['name']);
        $email = $row['email'];
        $parseWeak = ($email === '' || ($row['first_name'] === 'Candidate' && $row['last_name'] === ''));
        $alreadyOnJob = false;
        if ($email !== '') {
            $existing = CandidateService::findByEmail($email);
            if ($existing && CandidateService::applicationOnJob($existing['id'], $jobId)) {
                $alreadyOnJob = true;
            }
        }

        return [
            'file_index' => $fileIndex,
            'label' => $file['source_label'] ?? $file['name'],
            'file_name' => $file['name'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $email,
            'phone_country_code' => $row['phone_country_code'],
            'phone' => $row['phone'],
            'years_experience' => $row['years_experience'],
            'current_company' => $row['current_company'] ?? '',
            'parse_weak' => $parseWeak,
            'already_on_job' => $alreadyOnJob,
            'include' => !$alreadyOnJob,
        ];
    }

    private function bulkRowFromPreviewPost(array $rowIn)
    {
        return [
            'first_name' => trim($rowIn['first_name'] ?? '') !== '' ? trim($rowIn['first_name']) : 'Candidate',
            'last_name' => trim($rowIn['last_name'] ?? ''),
            'email' => trim($rowIn['email'] ?? ''),
            'phone_country_code' => trim($rowIn['phone_country_code'] ?? '') ?: '+91',
            'phone' => trim($rowIn['phone'] ?? ''),
            'years_experience' => isset($rowIn['years_experience']) && $rowIn['years_experience'] !== ''
                ? (float) $rowIn['years_experience'] : null,
            'current_company' => trim($rowIn['current_company'] ?? '') ?: null,
            'current_ctc' => trim($rowIn['current_ctc'] ?? '') ?: null,
            'expected_ctc' => trim($rowIn['expected_ctc'] ?? '') ?: null,
            'source_id' => !empty($rowIn['source_id']) ? $rowIn['source_id'] : $this->resolveSourceId($_POST),
        ];
    }

    /**
     * @return string added|skipped|failed
     */
    private function importOneBulkResume($jobId, $stageId, array $file, array $row, $index)
    {
        if ($row['email'] === '') {
            $row['email'] = 'import' . uuid() . '@pending.local';
        }
        if ($row['first_name'] === 'Candidate' && $row['last_name'] === '') {
            $row['last_name'] = (string) ($index + 1);
        }

        $existing = CandidateService::findByEmail($row['email']);
        if ($existing && CandidateService::applicationOnJob($existing['id'], $jobId)) {
            return 'skipped';
        }

        if ($existing) {
            $candidateId = $existing['id'];
            db()->update('candidates', [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone_country_code' => $row['phone_country_code'],
                'phone' => $row['phone'],
                'years_experience' => $row['years_experience'],
                'current_company' => $row['current_company'],
                'source_id' => $row['source_id'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $candidateId]);
        } else {
            $candidateId = uuid();
            db()->insert('candidates', [
                'id' => $candidateId,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone_country_code' => $row['phone_country_code'],
                'phone' => $row['phone'] ?: null,
                'years_experience' => $row['years_experience'],
                'current_company' => $row['current_company'],
                'source_id' => $row['source_id'],
            ]);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'txt') {
            try {
                $this->storeResume($file, $candidateId);
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect(url('candidates/create?job_id=' . $jobId));
            }
        }

        $appExtras = $this->normalizeApplicationCtc($row);
        if (!$existing) {
            $this->createApplication($candidateId, $jobId, $stageId, $appExtras);
        } elseif (!CandidateService::applicationOnJob($candidateId, $jobId)) {
            $this->createApplication($candidateId, $jobId, $stageId, $appExtras);
        }

        return 'added';
    }

    private function handleBulkImport($jobId, $stageId, $redirectAfter = null)
    {
        $redirectAfter = $redirectAfter ?: url('candidates');
        if (empty($_FILES['csv']['tmp_name'])) {
            flash('error', 'No CSV file uploaded.');
            redirect(url('candidates/create?job_id=' . $jobId));
        }
        $parser = new ResumeParserService();
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = array_map('trim', fgetcsv($handle) ?: []);
        $count = 0;
        $tempDir = BASE_PATH . '/storage/temp/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }
            $data = array_combine($header, $row);
            $email = trim($data['Email'] ?? '');
            if ($email === '') {
                continue;
            }
            $existing = CandidateService::findByEmail($email);
            if ($existing && CandidateService::applicationOnJob($existing['id'], $jobId)) {
                continue;
            }
            $sourceId = null;
            if (!empty($data['Source'])) {
                $src = db()->fetch('SELECT id FROM candidate_sources WHERE name = ?', [trim($data['Source'])]);
                $sourceId = $src ? $src['id'] : null;
            }
            if (!$sourceId) {
                $sourceId = $this->resolveSourceId($_POST);
            }
            if ($existing) {
                $candidateId = $existing['id'];
            } else {
                $candidateId = uuid();
                db()->insert('candidates', [
                    'id' => $candidateId,
                    'first_name' => $data['First Name'] ?? '',
                    'last_name' => $data['Last Name'] ?? '',
                    'email' => $email,
                    'phone_country_code' => isset($data['Country Code']) ? $data['Country Code'] : '+91',
                    'phone' => isset($data['Phone Number']) ? $data['Phone Number'] : '',
                    'source_id' => $sourceId,
                ]);
            }
            $resumeUrl = trim($data['Resume URL'] ?? '');
            if ($resumeUrl !== '') {
                try {
                    $ext = strtolower(pathinfo(parse_url($resumeUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'pdf';
                    $tmp = $tempDir . $candidateId . '_dl.' . $ext;
                    $parser->downloadResumeUrl($resumeUrl, $tmp);
                    $fakeFile = [
                        'name' => basename($tmp),
                        'tmp_name' => $tmp,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($tmp),
                    ];
                    $this->storeResume($fakeFile, $candidateId);
                    @unlink($tmp);
                } catch (Exception $e) {
                    // keep row without resume
                }
            }
            $this->createApplication($candidateId, $jobId, $stageId);
            $count++;
        }
        fclose($handle);
        flash('success', "$count candidates imported.");
        redirect($redirectAfter);
    }

    private function detectMeetingType($url)
    {
        if (strpos($url, 'zoom') !== false) return 'zoom';
        if (strpos($url, 'meet.google') !== false) return 'meet';
        if (strpos($url, 'teams') !== false) return 'teams';
        return 'external';
    }

    private function isTeamsJoinUrl($url)
    {
        $lower = strtolower($url);
        if (strpos($lower, 'teams') === false) {
            return false;
        }
        if (strpos($lower, 'meetingrecap') !== false) {
            return false;
        }
        return true;
    }

    private function scheduleTimesFromPost(array $post)
    {
        $start = normalize_time_store($post['scheduled_start'] ?? null);
        $end = normalize_time_store($post['scheduled_end'] ?? null);
        if ($start && !$end) {
            $end = interview_default_end_time($start);
        }
        return [$start, $end];
    }
}
