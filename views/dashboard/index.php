<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => url('dashboard')],
    ['label' => 'Overview'],
];
?>

<!-- Candidate Statistics -->
<div class="page-actions-bar" style="margin-bottom:16px">
    <h2 style="font-size:1rem;font-weight:700;color:var(--gray-900)">Candidate Statistics</h2>
    <a href="<?= url('candidates/create') ?>" class="btn btn-primary btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Add Candidate
    </a>
</div>

<!-- Stats Row -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-label">Total Candidates</div>
        <div class="stat-num"><?= number_format($stats['total'] ?? 0) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Scheduled</div>
        <div class="stat-num"><?= number_format($stats['scheduled'] ?? 0) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Completed</div>
        <div class="stat-num"><?= number_format($stats['completed'] ?? 0) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Selected</div>
        <div class="stat-num"><?= number_format($stats['selected'] ?? 0) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Processing</div>
        <div class="stat-num"><?= number_format($stats['iv_processing'] ?? 0) ?></div>
    </div>
    <?php if (PermissionService::canAssignPending()): ?>
    <div class="stat-box">
        <div class="stat-label">Pending Recordings</div>
        <div class="stat-num"><?= number_format($stats['pending_recordings'] ?? 0) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Charts -->
<div class="charts-row">
    <!-- Candidate Success Rate -->
    <div class="chart-box">
        <div class="chart-box-header">
            <div>
                <h3>Candidate Success Rate</h3>
                <div class="chart-legend" style="margin-top:6px">
                    <span><span class="dot dot-purple"></span> Passed Candidates</span>
                    <span><span class="dot dot-pink"></span> Rejected Candidates</span>
                </div>
            </div>
            <select class="filter-select" style="font-size:0.8rem" id="successRatePeriod" aria-label="Success rate period"
                onchange="var u=new URL(window.location.href);u.searchParams.set('success_period',this.value);window.location.href=u.toString();">
                <option value="month" <?= ($successPeriod ?? 'month') === 'month' ? 'selected' : '' ?>>Month</option>
                <option value="week" <?= ($successPeriod ?? '') === 'week' ? 'selected' : '' ?>>Week</option>
                <option value="year" <?= ($successPeriod ?? '') === 'year' ? 'selected' : '' ?>>Year</option>
            </select>
        </div>
        <?php
        $srPoints = $successRate['points'] ?? [];
        $srPaths = $successRate['paths'] ?? ['passed_line' => '', 'passed_area' => '', 'rejected_line' => ''];
        $srN = max(count($srPoints), 1);
        $srStep = $srN > 1 ? 600 / ($srN - 1) : 0;
        ?>
        <div style="position:relative;height:130px;overflow:hidden">
            <svg viewBox="0 0 600 130" preserveAspectRatio="none" style="width:100%;height:100%">
                <defs>
                    <linearGradient id="gradPurple" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#7c3aed" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>
                <?php if (!empty($srPaths['passed_area'])): ?>
                <path d="<?= e($srPaths['passed_area']) ?>" fill="url(#gradPurple)"/>
                <?php endif; ?>
                <?php if (!empty($srPaths['passed_line'])): ?>
                <path d="<?= e($srPaths['passed_line']) ?>" fill="none" stroke="#7c3aed" stroke-width="2"/>
                <?php endif; ?>
                <?php if (!empty($srPaths['rejected_line'])): ?>
                <path d="<?= e($srPaths['rejected_line']) ?>" fill="none" stroke="#f472b6" stroke-width="2" stroke-dasharray="4 3"/>
                <?php endif; ?>
                <?php foreach ($srPoints as $idx => $pt): ?>
                <text class="chart-axis-label" x="<?= (int) round($srN > 1 ? $idx * $srStep : 300) ?>" y="128" font-size="9" text-anchor="middle"><?= e($pt['label']) ?></text>
                <?php endforeach; ?>
            </svg>
        </div>
        <p class="hint" style="margin-top:6px;font-size:0.75rem">Passed = advance/offer; rejected = reject decisions by period (from application updates).</p>
    </div>

    <!-- Interview Status donut -->
    <div class="chart-box">
        <div class="chart-box-header">
            <h3>Interview Status</h3>
            <select class="filter-select" style="font-size:0.8rem">
                <option>Today</option>
                <option>Week</option>
                <option>Month</option>
            </select>
        </div>
        <div class="donut-wrap">
            <svg class="donut-svg" viewBox="0 0 100 100">
                <?php
                $total_iv = ($stats['iv_pending'] ?? 0) + ($stats['iv_scheduled'] ?? 0) + ($stats['iv_completed'] ?? 0);
                $total_iv = max($total_iv, 1);
                $r = 38; $cx = 50; $cy = 50; $circ = 2 * M_PI * $r;
                $pct_p = ($stats['iv_pending'] ?? 0) / $total_iv;
                $pct_s = ($stats['iv_scheduled'] ?? 0) / $total_iv;
                $pct_c = ($stats['iv_completed'] ?? 0) / $total_iv;
                $gap = 2;
                ?>
                <circle class="donut-track" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke-width="14"/>
                <!-- Pending (red) -->
                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="#ef4444" stroke-width="14"
                    stroke-dasharray="<?= $circ * $pct_p - $gap ?> <?= $circ - ($circ * $pct_p - $gap) ?>"
                    stroke-dashoffset="<?= $circ * 0.25 ?>" transform="rotate(-90 <?= $cx ?> <?= $cy ?>)"/>
                <!-- Scheduled (blue) -->
                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="#7c3aed" stroke-width="14"
                    stroke-dasharray="<?= $circ * $pct_s - $gap ?> <?= $circ - ($circ * $pct_s - $gap) ?>"
                    stroke-dashoffset="<?= $circ * (0.25 - $pct_p) ?>" transform="rotate(-90 <?= $cx ?> <?= $cy ?>)"/>
                <!-- Completed (green) -->
                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="#22c55e" stroke-width="14"
                    stroke-dasharray="<?= $circ * $pct_c - $gap ?> <?= $circ - ($circ * $pct_c - $gap) ?>"
                    stroke-dashoffset="<?= $circ * (0.25 - $pct_p - $pct_s) ?>" transform="rotate(-90 <?= $cx ?> <?= $cy ?>)"/>
            </svg>
            <div class="donut-legend">
                <div class="donut-legend-item"><span class="dot" style="background:#ef4444"></span><span class="num"><?= str_pad($stats['iv_pending'] ?? 0, 2, '0', STR_PAD_LEFT) ?></span><span class="lbl">Pending</span></div>
                <div class="donut-legend-item"><span class="dot" style="background:#7c3aed"></span><span class="num"><?= str_pad($stats['iv_scheduled'] ?? 0, 2, '0', STR_PAD_LEFT) ?></span><span class="lbl">Scheduled</span></div>
                <div class="donut-legend-item"><span class="dot" style="background:#22c55e"></span><span class="num"><?= str_pad($stats['iv_completed'] ?? 0, 2, '0', STR_PAD_LEFT) ?></span><span class="lbl">Completed</span></div>
            </div>
        </div>
    </div>
</div>

<!-- Interviews section -->
<div class="interviews-section">
    <div class="interviews-header">
        <h2>Interviews</h2>
        <a href="<?= url('jobs/create') ?>" class="btn btn-primary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Add Job Role
        </a>
    </div>
    <div class="interview-cols">
        <!-- Pending -->
        <div class="iv-col">
            <div class="iv-col-header">
                <div class="col-title"><span class="dot dot-red"></span> Pending Interviews</div>
                <a href="<?= url('candidates?status=pending') ?>">View All</a>
            </div>
            <?php if (empty($pending)): ?>
            <div style="padding:20px 0;text-align:center;color:var(--text-muted);font-size:0.82rem">No pending interviews</div>
            <?php else: foreach ($pending as $iv): ?>
            <div class="iv-card">
                <div class="iv-card-name">
                    <strong><?= e($iv['first_name'] . ' ' . $iv['last_name']) ?></strong>
                    <div style="display:flex;gap:8px">
                        <?php if (config('app')['teams_keka_register_mode']): ?>
                        <a href="#" onclick="openRegisterTeamsDrawer('<?= e($iv['id']) ?>');return false">Link Teams</a>
                        <?php else: ?>
                        <a href="#" onclick="openInterviewDrawer('<?= e($iv['id']) ?>');return false">Schedule</a>
                        <?php endif; ?>
                        <?php if (config('app')['show_manual_video_upload']): ?>
                        <a href="#" onclick="openUploadDrawer('<?= e($iv['id']) ?>');return false">Upload</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="iv-card-job"><?= e($iv['job_title']) ?> – <?= e($iv['department_name'] ?? '') ?></div>
                <div class="iv-card-time">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <?= $iv['scheduled_date'] ? format_date($iv['scheduled_date']) : '–' ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Scheduled -->
        <div class="iv-col">
            <div class="iv-col-header">
                <div class="col-title"><span class="dot dot-blue"></span> Scheduled Interviews</div>
                <a href="<?= url('candidates?status=scheduled') ?>">View All</a>
            </div>
            <?php if (empty($scheduled)): ?>
            <div style="padding:20px 0;text-align:center;color:var(--text-muted);font-size:0.82rem">No scheduled interviews</div>
            <?php else: foreach ($scheduled as $iv): ?>
            <div class="iv-card">
                <div class="iv-card-name">
                    <strong><?= e($iv['first_name'] . ' ' . $iv['last_name']) ?></strong>
                    <?php if (config('app')['show_manual_video_upload']): ?>
                    <a href="#" onclick="openUploadDrawer('<?= e($iv['id']) ?>');return false">Upload</a>
                    <?php endif; ?>
                </div>
                <div class="iv-card-job"><?= e($iv['job_title']) ?> – <?= e($iv['department_name'] ?? '') ?></div>
                <div class="iv-card-time">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <?= $iv['scheduled_date'] ? format_date($iv['scheduled_date']) : '–' ?>
                    <?= format_interview_time_range($iv['scheduled_start'] ?? null, $iv['scheduled_end'] ?? null) ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Completed -->
        <div class="iv-col">
            <div class="iv-col-header">
                <div class="col-title"><span class="dot dot-green"></span> Recently Completed Interviews</div>
                <a href="<?= url('candidates?status=completed') ?>">View All</a>
            </div>
            <?php if (empty($completed)): ?>
            <div style="padding:20px 0;text-align:center;color:var(--text-muted);font-size:0.82rem">No completed interviews</div>
            <?php else: foreach ($completed as $iv): ?>
            <div class="iv-card">
                <div class="iv-card-name">
                    <strong><?= e($iv['first_name'] . ' ' . $iv['last_name']) ?></strong>
                    <?php if ($iv['report_id']): ?>
                    <a href="<?= url('jobs/' . $iv['job_id'] . '/reports/' . $iv['report_id']) ?>">View Report</a>
                    <?php endif; ?>
                </div>
                <div class="iv-card-job"><?= e($iv['job_title']) ?> – <?= e($iv['department_name'] ?? '') ?></div>
                <div class="iv-card-time">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <?= $iv['scheduled_date'] ? format_date($iv['scheduled_date']) : '–' ?>
                    <?= format_interview_time_range($iv['scheduled_start'] ?? null, $iv['scheduled_end'] ?? null) ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<?php if (config('app')['teams_keka_register_mode']): ?>
<?php include BASE_PATH . '/views/partials/register_teams_drawer.php'; ?>
<?php else: ?>
<?php include BASE_PATH . '/views/partials/interview_schedule_drawer.php'; ?>
<?php endif; ?>
<?php if (config('app')['show_manual_video_upload']): ?>
<?php include BASE_PATH . '/views/partials/interview_upload_drawer.php'; ?>
<?php endif; ?>
