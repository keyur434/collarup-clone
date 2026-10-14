<?php
$breadcrumbs = nav_trail_job($job, 'Interviews');
$activeTab = 'interviews';
?>

<!-- Job detail header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <h1 style="font-size:1rem;font-weight:700"><?= e($job['title']) ?></h1>
            <span class="badge badge-<?= e($job['status']) ?>"><?= e(ucfirst($job['status'])) ?></span>
        </div>
        <p style="font-size:0.82rem;color:var(--text-muted)">
            <?= e($job['city_name'] ?? '') ?><?= ($job['city_name'] && $job['state_name']) ? ', ' : '' ?><?= e($job['state_name'] ?? '') ?> · <?= e($job['seniority_name']) ?>
            · Created by <?= e(user_display_name($job['creator_first_name'] ?? '', $job['creator_last_name'] ?? '')) ?>
            <?= !empty($job['creator_email']) ? ' (' . e($job['creator_email']) . ')' : '' ?>
        </p>
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

<?php include BASE_PATH . '/views/partials/job_subtabs.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;flex-wrap:wrap">
    <p style="font-size:0.82rem;color:var(--text-muted);margin:0;max-width:720px">
        Each pipeline round has its own interview row. After <strong>Advance</strong> on a report, schedule the <strong>current round</strong> (highlighted) — link Teams, sync recording, then open the new report.
    </p>
    <?php if (!empty($pipelineStages)): ?>
    <form method="GET" action="<?= url('jobs/' . $job['id'] . '/interviews') ?>" style="display:flex;align-items:center;gap:8px">
        <label for="stage_id" style="font-size:0.78rem;color:var(--text-muted)">Stage</label>
        <select name="stage_id" id="stage_id" class="filter-select" style="padding:6px 10px;font-size:0.8rem" onchange="this.form.submit()">
            <option value="">All rounds</option>
            <?php foreach ($pipelineStages as $ps): ?>
            <option value="<?= e($ps['id']) ?>" <?= ($stageFilter ?? '') === $ps['id'] ? 'selected' : '' ?>><?= e($ps['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<!-- Table -->
<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table" data-sortable>
            <thead>
                <tr>
                    <?= th_sort('Candidate', 'string') ?>
                    <?= th_sort('Stage', 'string') ?>
                    <?= th_sort('Status', 'string') ?>
                    <?= th_sort('Date & Time', 'date') ?>
                    <?= th_sort('Score', 'number') ?>
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
                <tr data-interview-id="<?= e($iv['id']) ?>" class="<?= !empty($iv['is_current_round']) ? 'interview-row-current' : '' ?>">
                    <td data-label="Candidate">
                        <div class="candidate-cell">
                            <span class="av"><?= e(strtoupper(substr($iv['first_name'],0,1).substr($iv['last_name']??'',0,1))) ?></span>
                            <span style="font-weight:500;color:var(--gray-900)"><?= e($iv['first_name'] . ' ' . $iv['last_name']) ?></span>
                        </div>
                    </td>
                    <td data-label="Stage">
                        <?= e($iv['stage_name']) ?>
                        <?php if (!empty($iv['is_current_round'])): ?>
                        <span class="badge" style="margin-left:6px;font-size:0.68rem;background:var(--primary-soft, #e0e7ff);color:var(--primary)">Current</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Status">
                        <span class="badge badge-<?= e($iv['status']) ?>"><?= e(ucfirst($iv['status'])) ?></span>
                        <?php
                        $rfs = $iv['recording_fetch_state'] ?? '';
                        if (in_array($rfs, ['waiting', 'retry'], true) && !empty($iv['recording_fetch_after'])): ?>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px">Recording sync after <?= e(format_date($iv['recording_fetch_after'], 'd M g:i a')) ?></div>
                        <?php elseif ($rfs === 'waiting' || $rfs === 'retry'): ?>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px">Recording sync pending</div>
                        <?php elseif ($rfs === 'fetching'): ?>
                        <div style="font-size:0.72rem;color:var(--amber);margin-top:2px">Fetching recording…</div>
                        <?php elseif ($rfs === 'failed'): ?>
                        <div style="font-size:0.72rem;color:var(--red);margin-top:2px">Recording fetch failed</div>
                        <?php endif; ?>
                        <?php if (!empty($iv['meeting_url']) && empty($iv['teams_organizer_user_id']) && strpos(strtolower($iv['meeting_url']), 'teams') !== false): ?>
                        <div style="font-size:0.72rem;color:var(--red);margin-top:2px">Teams not registered — update link &amp; host email</div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Date" style="font-size:0.82rem;white-space:nowrap" data-sort-value="<?= e($iv['scheduled_date'] ?? '') ?>">
                        <?= $iv['scheduled_date'] ? format_date($iv['scheduled_date']) : '–' ?>
                        <?= format_interview_time_range($iv['scheduled_start'] ?? null, $iv['scheduled_end'] ?? null) ?>
                    </td>
                    <td data-label="Score" data-sort-value="<?= sort_data_val($iv['overall_score'] ?? '') ?>">
                        <?php if ($sl): ?>
                        <span class="score-badge <?= $sc ?>"><?= e($iv['overall_score']) ?> <?= e($sl['label']) ?></span>
                        <?php else: ?>–<?php endif; ?>
                    </td>
                    <td data-label="Action">
                        <?php if ($iv['status'] === 'completed' && !empty($iv['report_id'])): ?>
                        <a href="<?= url('jobs/' . $job['id'] . '/reports/' . $iv['report_id']) ?>" style="color:var(--primary);font-weight:600;font-size:0.82rem">View Report</a>
                        <?php elseif ($iv['status'] === 'processing'): ?>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">
                            <span style="font-size:0.82rem;color:var(--amber)">Processing…</span>
                            <?php if (!empty($iv['processing_hint']['message'])): ?>
                            <span style="font-size:0.72rem;color:var(--text-muted);max-width:220px"><?= e($iv['processing_hint']['message']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($canManageJob) && !empty($iv['processing_hint']['can_retry_analyze'])): ?>
                            <form method="POST" action="<?= url('interviews/' . $iv['id'] . '/requeue-analysis') ?>" style="margin:0">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.72rem;padding:2px 8px">Retry analysis</button>
                            </form>
                            <?php endif; ?>
                            <?php if (!empty($canManageJob) && !empty($iv['processing_hint']['can_sync_recording'])): ?>
                            <form method="POST" action="<?= url('interviews/' . $iv['id'] . '/fetch-recording') ?>" style="margin:0">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.72rem;padding:2px 8px">Sync recording</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($iv['status'] === 'scheduled' && !empty($iv['meeting_url'])): ?>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">
                            <a href="<?= e($iv['meeting_url']) ?>" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600;font-size:0.82rem">Join Teams</a>
                            <?php if (config('app')['teams_keka_register_mode']): ?>
                            <a href="#" onclick="openRegisterTeamsDrawer('<?= e($iv['id']) ?>');return false" style="font-size:0.78rem;color:var(--text-muted)">Update link</a>
                            <?php endif; ?>
                            <?php if (!empty($canManageJob) && in_array($iv['recording_fetch_state'] ?? '', ['waiting', 'retry', 'failed', 'fetching'], true)): ?>
                            <form method="POST" action="<?= url('interviews/' . $iv['id'] . '/fetch-recording') ?>" style="margin:0">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.72rem;padding:2px 8px">Sync recording now</button>
                            </form>
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
                                <option value="offer" <?= ($iv['decision'] ?? '') === 'offer' ? 'selected' : '' ?>>Offer</option>
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

<?php if (!empty($focusInterviewId)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var id = <?= json_encode($focusInterviewId) ?>;
    var row = document.querySelector('[data-interview-id="' + id + '"]');
    if (row) row.scrollIntoView({ block: 'center', behavior: 'smooth' });
    <?php if (config('app')['teams_keka_register_mode']): ?>
    if (typeof openRegisterTeamsDrawer === 'function') openRegisterTeamsDrawer(id);
    <?php else: ?>
    if (typeof openInterviewDrawer === 'function') openInterviewDrawer(id);
    <?php endif; ?>
});
</script>
<?php endif; ?>
