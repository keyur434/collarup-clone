<?php

class CandidateController
{
    public function index()
    {
        $perPage = isset($_GET['perPage']) ? (int) $_GET['perPage'] : 15;
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
        if (!PermissionService::isAdminRole()) {
            $userId = auth_id();
            $where .= ' AND (EXISTS (SELECT 1 FROM job_hiring_managers jhm WHERE jhm.job_id = a.job_id AND jhm.user_id = ?)
                          OR EXISTS (SELECT 1 FROM job_interviewers ji WHERE ji.job_id = a.job_id AND ji.user_id = ?))';
            $params[] = $userId;
            $params[] = $userId;
        }

        $total = db()->fetch(
            "SELECT COUNT(*) as c FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id WHERE $where",
            $params
        )['c'];

        $candidates = db()->fetchAll(
            "SELECT a.id as application_id, a.job_id, a.candidate_id, a.status, a.decision, a.application_date, a.created_at, a.is_archived,
                    c.first_name, c.last_name, c.email, c.resume_path, j.title as job_title, d.name as department_name,
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
             LEFT JOIN job_pipeline_stages ps ON ps.id = a.current_stage_id
             WHERE $where
             ORDER BY a.created_at DESC" . sql_page($perPage, $offset),
            $params
        );

        render('candidates.index', [
            'title' => 'Candidates',
            'candidates' => $candidates,
            'departments' => db()->fetchAll('SELECT * FROM departments ORDER BY name'),
            'jobs' => db()->fetchAll("SELECT id, title FROM jobs WHERE status = 'active' ORDER BY title"),
            'search' => $search,
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
                if (!empty($ctx['resume_path'])) {
                    $file = BASE_PATH . '/storage/resumes/' . $ctx['resume_path'];
                    if (file_exists($file)) {
                        @unlink($file);
                    }
                }
                $candidateId = $ctx['candidate_id'];
                db()->query('DELETE FROM applications WHERE id = ?', [$applicationId]);
                $remaining = db()->fetch('SELECT COUNT(*) as c FROM applications WHERE candidate_id = ?', [$candidateId])['c'];
                if ((int) $remaining === 0) {
                    db()->query('DELETE FROM candidates WHERE id = ?', [$candidateId]);
                }
                ActivityLogService::log('candidate_delete', 'application', $applicationId);
                $count++;
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
        $archiveCurrent = !empty($_POST['archive_current']);

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

        render('candidates.edit', [
            'title' => 'Manage Candidate',
            'app' => $ctx,
            'jobs' => db()->fetchAll("SELECT id, title FROM jobs WHERE status = 'active' ORDER BY title"),
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

        db()->update('applications', [
            'current_stage_id' => $_POST['pipeline_id'] ?: $ctx['current_stage_id'],
            'status' => $_POST['status'] ?? $ctx['status'],
            'decision' => $_POST['decision'] ?? $ctx['decision'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $applicationId]);

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

        db()->update('applications', [
            'is_archived' => 1,
            'status' => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $applicationId]);

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

        if (!empty($ctx['resume_path'])) {
            $file = BASE_PATH . '/storage/resumes/' . $ctx['resume_path'];
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $candidateId = $ctx['candidate_id'];
        db()->query('DELETE FROM applications WHERE id = ?', [$applicationId]);

        $remaining = db()->fetch('SELECT COUNT(*) as c FROM applications WHERE candidate_id = ?', [$candidateId])['c'];
        if ((int) $remaining === 0) {
            db()->query('DELETE FROM candidates WHERE id = ?', [$candidateId]);
        }

        ActivityLogService::log('candidate_delete', 'application', $applicationId);
        flash('success', 'Candidate application removed.');
        redirect(url('candidates'));
    }

    private function applicationContext($applicationId)
    {
        $row = db()->fetch(
            'SELECT a.*, c.first_name, c.last_name, c.email, c.phone, c.phone_country_code,
                    c.resume_path, c.years_experience, c.current_company, c.current_location, c.source_id,
                    j.title as job_title
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
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
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
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

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
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
            'jobs' => db()->fetchAll("SELECT id, title FROM jobs WHERE status = 'active' ORDER BY title"),
            'sources' => db()->fetchAll('SELECT * FROM candidate_sources ORDER BY name'),
            'selectedJobId' => $jobId,
            'selectedPipelineId' => $pipelineId,
            'activeNav' => 'candidates',
        ]);
    }

    public function store()
    {
        verify_csrf();
        $tab = isset($_POST['tab']) ? $_POST['tab'] : 'individual';
        if (!empty($_POST['first_name'])) {
            $tab = 'individual';
        } elseif (!empty($_FILES['csv']['name'])) {
            $tab = 'bulk';
        } elseif (!empty($_FILES['resumes']['name']) || (isset($_FILES['resumes']['name']) && is_array($_FILES['resumes']['name']) && $_FILES['resumes']['name'][0])) {
            $tab = 'resume';
        }
        $jobId = $_POST['job_id'];
        $stageId = $_POST['pipeline_id'];

        if ($tab === 'bulk') {
            $this->handleBulkImport($jobId, $stageId);
            return;
        }

        if ($tab === 'resume') {
            $this->handleResumeUpload($jobId, $stageId);
            return;
        }

        $email = trim($_POST['email']);
        $existing = CandidateService::findByEmail($email);

        if ($existing) {
            $onJob = CandidateService::applicationOnJob($existing['id'], $jobId);
            if ($onJob) {
                flash('error', 'This email is already on this job.');
                redirect(url('applications/' . $onJob['id'] . '/manage'));
            }
            db()->update('candidates', [
                'first_name' => trim($_POST['first_name']),
                'last_name' => trim($_POST['last_name']),
                'phone_country_code' => $_POST['phone_country_code'] ?: '+91',
                'phone' => $_POST['phone'],
                'source_id' => $_POST['source_id'] ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $existing['id']]);
            if (!empty($_FILES['resume']['name'])) {
                try {
                    $this->replaceResume($_FILES['resume'], $existing['id'], $existing['resume_path']);
                } catch (RuntimeException $e) {
                    flash('error', $e->getMessage());
                    redirect(url('candidates/create?job_id=' . $jobId));
                }
            }
            $this->createApplication($existing['id'], $jobId, $stageId);
            ActivityLogService::log('candidate_reuse', 'candidate', $existing['id'], ['job_id' => $jobId]);
            flash('success', 'Existing candidate added to this job role.');
            redirect(url('candidates'));
        }

        $candidateId = uuid();
        db()->insert('candidates', [
            'id' => $candidateId,
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => $email,
            'phone_country_code' => $_POST['phone_country_code'] ?: '+91',
            'phone' => $_POST['phone'],
            'source_id' => $_POST['source_id'] ?: null,
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
        redirect(url('candidates'));
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
        json_response(['interview' => $interview]);
    }

    public function updateDetails($id)
    {
        verify_csrf();
        $interview = db()->fetch('SELECT * FROM interviews WHERE id = ?', [$id]);
        if (!$interview) {
            flash('error', 'Interview not found.');
            redirect(url('candidates'));
        }

        db()->update('interviews', [
            'stage_id' => $_POST['stage_id'],
            'scheduled_date' => $_POST['scheduled_date'] ?: null,
            'scheduled_start' => $_POST['scheduled_start'] ?: null,
            'scheduled_end' => $_POST['scheduled_end'] ?: null,
            'meeting_url' => $_POST['meeting_url'] ?: null,
            'status' => $_POST['scheduled_date'] ? 'scheduled' : 'pending',
        ], 'id = :id', ['id' => $id]);

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

        if (!empty($_POST['scheduled_date'])) {
            try {
                $notify = new NotificationService();
                $notify->notifyInterviewScheduled($id);
            } catch (Exception $e) {
                // non-fatal
            }
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

        $redirect = InterviewWorkflowService::redirectUrl($id);
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

            if ($this->detectMeetingType($meetingUrl) === 'teams') {
                $teams = new TeamsRecordingService();
                if ($teams->isConfigured()) {
                    $queue = new QueueService();
                    $queue->push('FetchTeamsRecording', [
                        'interview_id' => $id,
                        'media_id' => $mediaId,
                    ]);
                    flash('success', 'Teams link saved. Fetching recording from Microsoft...');
                } else {
                    flash('success', 'Meeting link saved. Configure Graph sync app to auto-fetch Teams recordings.');
                }
            } else {
                flash('success', 'Meeting link saved. Upload the recorded interview video to run AI analysis.');
            }
        }

        redirect($redirect);
    }

    private function createApplication($candidateId, $jobId, $stageId)
    {
        $appId = uuid();
        db()->insert('applications', [
            'id' => $appId,
            'candidate_id' => $candidateId,
            'job_id' => $jobId,
            'current_stage_id' => $stageId,
            'status' => 'pending',
            'application_date' => date('Y-m-d'),
        ]);

        $interviewId = uuid();
        db()->insert('interviews', [
            'id' => $interviewId,
            'application_id' => $appId,
            'stage_id' => $stageId,
            'status' => 'pending',
        ]);
    }

    private function handleResumeUpload($jobId, $stageId)
    {
        if (empty($_FILES['resumes'])) {
            flash('error', 'No files uploaded.');
            redirect(url('candidates/create?job_id=' . $jobId));
        }
        $files = $_FILES['resumes'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < $count; $i++) {
            $candidateId = uuid();
            db()->insert('candidates', [
                'id' => $candidateId,
                'first_name' => 'Candidate',
                'last_name' => ($i + 1),
                'email' => 'candidate' . $i . '@pending.local',
                'source_id' => $_POST['source_id'] ?: null,
            ]);
            $file = [
                'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                'error' => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                'size' => is_array($files['size']) ? $files['size'][$i] : $files['size'],
            ];
            try {
                $this->storeResume($file, $candidateId);
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect(url('candidates/create?job_id=' . $jobId));
            }
            $this->createApplication($candidateId, $jobId, $stageId);
        }
        flash('success', "$count resume(s) uploaded.");
        redirect(url('candidates'));
    }

    private function handleBulkImport($jobId, $stageId)
    {
        if (empty($_FILES['csv']['tmp_name'])) {
            flash('error', 'No CSV file uploaded.');
            redirect(url('candidates/create?job_id=' . $jobId));
        }
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($handle);
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            $candidateId = uuid();
            db()->insert('candidates', [
                'id' => $candidateId,
                'first_name' => $data['First Name'],
                'last_name' => $data['Last Name'],
                'email' => $data['Email'],
                'phone_country_code' => isset($data['Country Code']) ? $data['Country Code'] : '+91',
                'phone' => isset($data['Phone Number']) ? $data['Phone Number'] : '',
            ]);
            $this->createApplication($candidateId, $jobId, $stageId);
            $count++;
        }
        fclose($handle);
        flash('success', "$count candidates imported.");
        redirect(url('candidates'));
    }

    private function detectMeetingType($url)
    {
        if (strpos($url, 'zoom') !== false) return 'zoom';
        if (strpos($url, 'meet.google') !== false) return 'meet';
        if (strpos($url, 'teams') !== false) return 'teams';
        return 'external';
    }
}
