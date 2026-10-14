<?php

class HiringDeskController
{
    public function index()
    {
        HiringDeskService::assertEnabled();

        render('hiring-desk.index', [
            'title' => 'Hiring desk (preview)',
            'jobs' => HiringDeskService::listJobs(),
            'activeNav' => 'hiring-desk',
        ]);
    }

    public function show($jobId)
    {
        HiringDeskService::assertEnabled();

        $job = db()->fetch(
            'SELECT j.*, d.name AS department_name FROM jobs j LEFT JOIN departments d ON d.id = j.department_id WHERE j.id = ?',
            [$jobId]
        );
        if (!$job) {
            http_response_code(404);
            die('Job not found');
        }
        PermissionService::requireJobAccess($jobId);

        $filters = [
            'decision' => isset($_GET['decision']) ? trim($_GET['decision']) : '',
            'has_report' => !empty($_GET['has_report']),
        ];

        $deskConfig = HiringDeskConfigService::get($jobId);
        $allApplicants = HiringDeskService::applicantsForJob($jobId, []);
        $applicants = HiringDeskService::filterApplicants($allApplicants, $filters);
        foreach ($applicants as &$a) {
            $assist = HiringDeskConfigService::assistScore($a, $deskConfig);
            $a['assist_composite'] = $assist['composite'];
            $a['passes_bar'] = $assist['passes_bar'];
            $a['bar_failures'] = $assist['bar_failures'];
        }
        unset($a);
        HiringDeskService::assignPoolRanks($applicants);

        $distribution = HiringDeskService::scoreDistribution($allApplicants);
        $insightDisplay = HiringDeskInsightService::getForDisplay($jobId);
        $insightNameMap = [];
        foreach ($allApplicants as $a) {
            $insightNameMap[$a['application_id']] = trim($a['first_name'] . ' ' . $a['last_name']);
        }

        $rubricCriteria = db()->fetchAll(
            'SELECT name FROM job_rubric_criteria WHERE job_id = ? AND is_active = 1 ORDER BY sort_order',
            [$jobId]
        );

        render('hiring-desk.show', [
            'title' => 'Hiring desk — ' . $job['title'],
            'job' => $job,
            'applicants' => $applicants,
            'distribution' => $distribution,
            'filters' => $filters,
            'deskConfig' => $deskConfig,
            'rubricCriteria' => $rubricCriteria,
            'insightDisplay' => $insightDisplay,
            'insightNameMap' => $insightNameMap,
            'poolRank' => HiringDeskPoolRankService::getStored($jobId),
            'decisionNotes' => HiringDeskDecisionService::listForJob($jobId),
            'decisionLogApplicants' => $allApplicants,
            'activeNav' => 'hiring-desk',
        ]);
    }

    private function requireDeskManage($jobId)
    {
        PermissionService::requireJobAccess($jobId);
        PermissionService::requireManageJob($jobId);
    }

    public function generateInsights($jobId)
    {
        HiringDeskService::assertEnabled();
        verify_csrf();
        $this->requireDeskManage($jobId);

        try {
            HiringDeskInsightService::generate($jobId, auth_id());
            flash('success', 'AI hiring brief generated.');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }

        redirect(url('hiring-desk/jobs/' . $jobId . '#hdAiBrief'));
    }

    public function scoreResumes($jobId)
    {
        HiringDeskService::assertEnabled();
        verify_csrf();
        $this->requireDeskManage($jobId);

        try {
            $result = HiringDeskResumeMatchService::scoreJobPool($jobId);
            $msg = 'Resume vs JD: processed ' . (int) $result['scored'] . ' resume(s)';
            if ((int) $result['skipped'] > 0) {
                $msg .= ', skipped ' . (int) $result['skipped'] . ' (no file or could not read text)';
            }
            if (!empty($result['ungraded'])) {
                $msg .= ', ' . (int) $result['ungraded'] . ' could not be graded (AI response)';
            }
            $msg .= '. ' . (int) $result['graded'] . ' row(s) now have grades. See Resume vs JD below.';
            flash('success', $msg);
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
        redirect(url('hiring-desk/jobs/' . $jobId . '#hdPoolTable'));
    }

    public function generatePoolRank($jobId)
    {
        HiringDeskService::assertEnabled();
        verify_csrf();
        $this->requireDeskManage($jobId);

        try {
            HiringDeskPoolRankService::generate($jobId, auth_id());
            flash('success', 'Pool ranking generated.');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
        redirect(url('hiring-desk/jobs/' . $jobId . '#hdPoolRank'));
    }

    public function saveConfig($jobId)
    {
        HiringDeskService::assertEnabled();
        verify_csrf();
        $this->requireDeskManage($jobId);

        $weights = [
            'overall' => (int) ($_POST['weights']['overall'] ?? 25),
            'experience' => (int) ($_POST['weights']['experience'] ?? 25),
            'culture' => (int) ($_POST['weights']['culture'] ?? 25),
            'soft_skills' => (int) ($_POST['weights']['soft_skills'] ?? 25),
            'criteria' => isset($_POST['weights_criteria']) && is_array($_POST['weights_criteria'])
                ? $_POST['weights_criteria'] : [],
        ];
        $minimums = isset($_POST['minimums']) && is_array($_POST['minimums']) ? $_POST['minimums'] : [];

        try {
            HiringDeskConfigService::save($jobId, $weights, $minimums);
            flash('success', 'Scoring setup saved.');
        } catch (Exception $e) {
            flash('error', 'Could not save scoring setup. Ensure hiring desk migrations are applied.');
        }
        redirect(url('hiring-desk/jobs/' . $jobId . '#hdScoringConfig'));
    }

    public function recordDecisionNote($jobId)
    {
        HiringDeskService::assertEnabled();
        verify_csrf();
        $this->requireDeskManage($jobId);

        try {
            HiringDeskDecisionService::record(
                $jobId,
                $_POST['application_id'] ?? '',
                $_POST['note_type'] ?? 'general',
                $_POST['reason'] ?? ''
            );
            flash('success', 'Decision note logged.');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
        redirect(url('hiring-desk/jobs/' . $jobId . '#hdDecisionLog'));
    }

    public function exportPack($jobId)
    {
        HiringDeskService::assertEnabled();
        PermissionService::requireJobAccess($jobId);

        $job = db()->fetch('SELECT j.*, d.name AS department_name FROM jobs j LEFT JOIN departments d ON d.id = j.department_id WHERE j.id = ?', [$jobId]);
        if (!$job) {
            http_response_code(404);
            die('Job not found');
        }
        $deskConfig = HiringDeskConfigService::get($jobId);
        $applicants = HiringDeskService::applicantsForJob($jobId, []);
        foreach ($applicants as &$a) {
            $assist = HiringDeskConfigService::assistScore($a, $deskConfig);
            $a['assist_composite'] = $assist['composite'];
        }
        unset($a);

        $insight = HiringDeskInsightService::getForDisplay($jobId);
        $insightNameMap = [];
        foreach ($applicants as $a) {
            $insightNameMap[$a['application_id']] = trim($a['first_name'] . ' ' . $a['last_name']);
        }

        $html = view('hiring-desk.export', [
            'title' => 'Committee pack',
            'job' => $job,
            'applicants' => $applicants,
            'insightData' => $insight['data'],
            'poolRank' => HiringDeskPoolRankService::getStored($jobId),
            'decisionNotes' => HiringDeskDecisionService::listForJob($jobId),
            'insightNameMap' => $insightNameMap,
        ]);

        $filename = 'hiring-desk-' . preg_replace('/[^a-z0-9]+/i', '-', $job['title']) . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $html;
        exit;
    }

    public function compare($jobId)
    {
        HiringDeskService::assertEnabled();

        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        if (!$job) {
            http_response_code(404);
            die('Job not found');
        }
        PermissionService::requireJobAccess($jobId);

        $ids = isset($_GET['ids']) ? $_GET['ids'] : '';
        if ($ids === '' && !empty($_GET['application_ids']) && is_array($_GET['application_ids'])) {
            $ids = implode(',', $_GET['application_ids']);
        }
        $applicationIds = array_filter(array_map('trim', explode(',', $ids)));
        $requestedCount = count($applicationIds);

        $candidates = HiringDeskService::compareCandidates($jobId, $applicationIds);
        if (count($candidates) < 2) {
            flash('error', 'Select at least 2 candidates in the preview hiring desk.');
            redirect(url('hiring-desk/jobs/' . $jobId));
        }

        render('hiring-desk.compare', [
            'title' => 'Compare (preview)',
            'job' => $job,
            'candidates' => $candidates,
            'compareTruncated' => $requestedCount > 8,
            'activeNav' => 'hiring-desk',
        ]);
    }
}
