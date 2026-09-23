<?php
$breadcrumbs = [
    ['label' => $job['title'], 'url' => url('jobs/' . $job['id'] . '/interviews')],
    ['label' => 'Interviews'],
];
?>

<!-- Job detail header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <h1 style="font-size:1rem;font-weight:700"><?= e($job['title']) ?></h1>
            <span class="badge badge-<?= e($job['status']) ?>"><?= e(ucfirst($job['status'])) ?></span>
        </div>
        <p style="font-size:0.82rem;color:var(--text-muted)"><?= e($job['city_name'] ?? '') ?><?= ($job['city_name'] && $job['state_name']) ? ', ' : '' ?><?= e($job['state_name'] ?? '') ?> · <?= e($job['seniority_name']) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php if (!empty($canManageJob)): ?>
        <a href="<?= url('jobs/edit/' . $job['id']) ?>" class="btn btn-outline btn-sm">Edit job</a>
        <form method="POST" action="<?= url('jobs/' . $job['id'] . '/status') ?>" style="display:inline-flex;align-items:center;gap:6px">
            <?= csrf_field() ?>
            <select name="status" class="filter-select" style="padding:6px 10px;font-size:0.8rem" onchange="this.form.submit()">
                <option value="active" <?= $job['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $job['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="archived" <?= $job['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </form>
        <?php endif; ?>
        <a href="<?= url('jobs/' . $job['id'] . '/compare') ?>" class="btn btn-outline btn-sm">Compare</a>
        <a href="<?= url('candidates/create?job_id=' . $job['id']) ?>" class="btn btn-primary btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Add Candidate
        </a>
    </div>
</div>

<!-- Sub-tabs -->
<div style="display:flex;border-bottom:1px solid var(--border);margin-bottom:16px">
    <a href="<?= url('jobs/' . $job['id'] . '/interviews?tab=applicant-pool') ?>"
       style="padding:8px 16px;font-size:0.875rem;font-weight:500;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap"
       class="<?= ($tab ?? '') === 'applicant-pool' ? 'subtab-active' : '' ?>">Applicant Pool</a>
    <a href="<?= url('jobs/' . $job['id'] . '/interviews') ?>"
       style="padding:8px 16px;font-size:0.875rem;font-weight:500;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap"
       class="<?= ($tab ?? 'interviews') === 'interviews' ? 'subtab-active' : '' ?>">Interviews</a>
</div>
<style>
.subtab-active { color: var(--primary) !important; border-bottom-color: var(--primary) !important; font-weight: 600 !important; }
</style>

<!-- Table -->
<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Stage</th>
                    <th>Status</th>
                    <th>Date &amp; Time</th>
                    <th>Score</th>
                    <th>Action</th>
                    <th>Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($interviews)): ?>
                <tr><td colspan="7" class="empty-state">No interviews yet for this role.</td></tr>
                <?php else: foreach ($interviews as $iv):
                    $sl = $iv['overall_score'] ? score_label($iv['overall_score']) : null;
                    $sc = $sl ? ($sl['class'] === 'score-good' ? 'good' : ($sl['class'] === 'score-average' ? 'avg' : 'poor')) : '';
                ?>
                <tr>
                    <td data-label="Candidate">
                        <div class="candidate-cell">
                            <span class="av"><?= e(strtoupper(substr($iv['first_name'],0,1).substr($iv['last_name']??'',0,1))) ?></span>
                            <span style="font-weight:500;color:var(--gray-900)"><?= e($iv['first_name'] . ' ' . $iv['last_name']) ?></span>
                        </div>
                    </td>
                    <td data-label="Stage"><?= e($iv['stage_name']) ?></td>
                    <td data-label="Status">
                        <span class="badge badge-<?= e($iv['status']) ?>"><?= e(ucfirst($iv['status'])) ?></span>
                        <?php if (!empty($iv['teams_meeting_id']) && ($iv['recording_fetch_state'] ?? '') === 'waiting'): ?>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px">Recording after call</div>
                        <?php elseif (($iv['recording_fetch_state'] ?? '') === 'fetching'): ?>
                        <div style="font-size:0.72rem;color:var(--amber);margin-top:2px">Fetching recording…</div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Date" style="font-size:0.82rem;white-space:nowrap">
                        <?= $iv['scheduled_date'] ? format_date($iv['scheduled_date']) : '–' ?>
                        <?= ($iv['scheduled_start'] && $iv['scheduled_end']) ? ' · ' . format_time($iv['scheduled_start']) . '–' . format_time($iv['scheduled_end']) : '' ?>
                    </td>
                    <td data-label="Score">
                        <?php if ($sl): ?>
                        <span class="score-badge <?= $sc ?>"><?= e($iv['overall_score']) ?> <?= e($sl['label']) ?></span>
                        <?php else: ?>–<?php endif; ?>
                    </td>
                    <td data-label="Action">
                        <?php if ($iv['status'] === 'completed' && !empty($iv['report_id'])): ?>
                        <a href="<?= url('jobs/' . $job['id'] . '/reports/' . $iv['report_id']) ?>" style="color:var(--primary);font-weight:600;font-size:0.82rem">View Report</a>
                        <?php elseif ($iv['status'] === 'processing'): ?>
                        <span style="font-size:0.82rem;color:var(--amber)">Processing…</span>
                        <?php elseif ($iv['status'] === 'scheduled' && !empty($iv['meeting_url'])): ?>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">
                            <a href="<?= e($iv['meeting_url']) ?>" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600;font-size:0.82rem">Join Teams</a>
                            <?php if (config('app')['teams_keka_register_mode']): ?>
                            <a href="#" onclick="openRegisterTeamsDrawer('<?= e($iv['id']) ?>');return false" style="font-size:0.78rem;color:var(--text-muted)">Update link</a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">
                            <?php if (config('app')['teams_keka_register_mode'] && in_array($iv['status'], ['pending', 'scheduled'], true)): ?>
                            <a href="#" onclick="openRegisterTeamsDrawer('<?= e($iv['id']) ?>');return false" style="color:var(--primary);font-weight:600;font-size:0.82rem">Link Teams meeting</a>
                            <?php elseif (in_array($iv['status'], ['pending', 'scheduled'], true)): ?>
                            <a href="#" onclick="openInterviewDrawer('<?= e($iv['id']) ?>');return false" style="color:var(--primary);font-weight:600;font-size:0.82rem">Schedule</a>
                            <?php endif; ?>
                            <?php if (!config('app')['teams_keka_register_mode']): ?>
                            <a href="#" onclick="openInterviewDrawer('<?= e($iv['id']) ?>');return false" style="font-size:0.78rem;color:var(--text-muted)">Edit details</a>
                            <?php endif; ?>
                            <?php if (config('app')['show_manual_video_upload']): ?>
                            <a href="#" onclick="openUploadDrawer('<?= e($iv['id']) ?>');return false" style="color:var(--text-muted);font-size:0.78rem">Upload video (support)</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Decision">
                        <?php if (!empty($iv['report_id'])): ?>
                        <form method="POST" action="<?= url('jobs/' . $job['id'] . '/reports/' . $iv['report_id'] . '/decision') ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <select name="decision" onchange="if(this.value){this.form.submit()}" style="padding:4px 8px;border:1px solid var(--gray-300);border-radius:var(--radius-sm);font-size:0.78rem;background:var(--surface)">
                                <option value="">Select…</option>
                                <option value="advance" <?= ($iv['decision'] ?? '') === 'advance' ? 'selected' : '' ?>>Advance</option>
                                <option value="hold" <?= ($iv['decision'] ?? '') === 'hold' ? 'selected' : '' ?>>Hold</option>
                                <option value="reject" <?= ($iv['decision'] ?? '') === 'reject' ? 'selected' : '' ?>>Reject</option>
                            </select>
                        </form>
                        <?php else: ?>
                        <span style="font-size:0.78rem;color:var(--text-muted)">–</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <div class="items-per-page">Items per page <select><option>15</option><option>25</option></select></div>
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
