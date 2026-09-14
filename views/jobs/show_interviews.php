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
    <a href="<?= url('candidates/create?job_id=' . $job['id']) ?>" class="btn btn-primary btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Add Candidate
    </a>
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
                    <td data-label="Status"><span class="badge badge-<?= e($iv['status']) ?>"><?= e(ucfirst($iv['status'])) ?></span></td>
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
                        <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">
                            <?php if ($iv['status'] === 'pending'): ?>
                            <a href="#" onclick="openInterviewDrawer('<?= e($iv['id']) ?>');return false" style="color:var(--primary);font-weight:600;font-size:0.82rem">Add Slot</a>
                            <?php endif; ?>
                            <a href="#" onclick="openUploadDrawer('<?= e($iv['id']) ?>');return false" style="color:var(--primary);font-weight:600;font-size:0.82rem">Upload Interview</a>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Decision">
                        <select style="padding:4px 8px;border:1px solid var(--gray-300);border-radius:var(--radius-sm);font-size:0.78rem;background:var(--surface)">
                            <option>Select…</option>
                            <option>Advance</option>
                            <option>Hold</option>
                            <option>Reject</option>
                        </select>
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

<?php include BASE_PATH . '/views/partials/interview_schedule_drawer.php'; ?>
<?php include BASE_PATH . '/views/partials/interview_upload_drawer.php'; ?>
