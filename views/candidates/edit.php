<?php
$breadcrumbs = nav_trail_dashboard(
    'Candidates',
    url('candidates'),
    trim($app['first_name'] . ' ' . $app['last_name'])
);
$archived = !empty($app['is_archived']);
$resumeExt = !empty($app['resume_path']) ? strtolower(pathinfo($app['resume_path'], PATHINFO_EXTENSION)) : '';
$canPreviewPdf = $resumeExt === 'pdf';
?>

<div class="page-header">
    <div>
        <h1>Manage Candidate</h1>
        <p><?= e($app['first_name'] . ' ' . $app['last_name']) ?> — <?= e($app['job_title']) ?></p>
        <p class="hint" style="margin-top:6px">
            Recruiter / HR: <strong><?= e(user_display_name($app['recruiter_first_name'] ?? '', $app['recruiter_last_name'] ?? '')) ?></strong>
            · Job created by: <strong><?= e(user_display_name($app['job_creator_first_name'] ?? '', $app['job_creator_last_name'] ?? '')) ?></strong>
        </p>
        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
            <?php if ($archived): ?><span class="badge badge-gray">Archived</span><?php endif; ?>
            <?php if (($emailAppCount ?? 1) > 1): ?>
            <span class="badge badge-amber" title="Same email on multiple job applications">Duplicate email (<?= (int) $emailAppCount ?> jobs)</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($archived && $canManage): ?>
    <form method="POST" action="<?= url('applications/' . $app['id'] . '/reactivate') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-sm">Reactivate</button>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($otherApplications)): ?>
<div class="card form-card" style="margin-bottom:16px">
    <h3 style="font-size:0.9rem;margin-bottom:10px">Other job applications (same person)</h3>
    <ul style="margin:0;padding-left:18px;font-size:0.85rem">
        <?php foreach ($otherApplications as $oa): ?>
        <li style="margin-bottom:6px;display:flex;flex-wrap:wrap;align-items:center;gap:6px">
            <a href="<?= url('applications/' . $oa['id'] . '/manage') ?>"><?= e($oa['job_title']) ?></a>
            — <?= e(ucfirst($oa['status'])) ?>
            <?php if (!empty($oa['is_archived'])): ?><span class="badge badge-gray" style="font-size:0.65rem">Archived</span><?php endif; ?>
            <?php if ($canManage && empty($oa['is_archived'])): ?>
            <form method="POST" action="<?= url('applications/' . $oa['id'] . '/archive') ?>" style="display:inline;margin:0" onsubmit="return confirm('Remove this candidate from <?= e($oa['job_title']) ?>? They will stay on other jobs.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.68rem;padding:2px 8px">Remove from job</button>
            </form>
            <?php elseif ($canManage && !empty($oa['is_archived'])): ?>
            <form method="POST" action="<?= url('applications/' . $oa['id'] . '/delete') ?>" style="display:inline;margin:0" onsubmit="return confirm('Permanently delete this archived application?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.68rem;padding:2px 8px">Delete listing</button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" action="<?= url('applications/' . $app['id'] . '/manage') ?>" enctype="multipart/form-data" class="card form-card">
    <?= csrf_field() ?>

    <?php if (!empty($recruiters)): ?>
    <h3 style="font-size:0.9rem;margin-bottom:12px">Ownership</h3>
    <div class="form-group" style="margin-bottom:16px">
        <label>Recruiter / HR owner</label>
        <select name="recruiter_id">
            <option value="">Unassigned</option>
            <?php foreach ($recruiters as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= (int) ($app['recruiter_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= e(user_display_name($r['first_name'], $r['last_name'])) ?> (<?= e($r['email']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <h3 style="font-size:0.9rem;margin-bottom:12px">Profile</h3>
    <div class="form-grid cols-2">
        <div class="form-group"><label>First Name *</label><input name="first_name" value="<?= e($app['first_name']) ?>" required></div>
        <div class="form-group"><label>Last Name *</label><input name="last_name" value="<?= e($app['last_name']) ?>" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= e($app['email']) ?>" required></div>
        <div class="form-group"><label>Phone</label>
            <div style="display:flex;gap:8px">
                <input name="phone_country_code" value="<?= e($app['phone_country_code'] ?: '+91') ?>" style="width:72px">
                <input name="phone" value="<?= e($app['phone']) ?>" style="flex:1">
            </div>
        </div>
        <div class="form-group"><label>Years of Experience</label><input type="number" step="0.1" name="years_experience" value="<?= e($app['years_experience']) ?>"></div>
        <div class="form-group"><label>Current Company</label><input name="current_company" value="<?= e($app['current_company']) ?>"></div>
        <div class="form-group"><label>Current Location</label><input name="current_location" value="<?= e($app['current_location']) ?>"></div>
        <div class="form-group"><label>Source</label>
            <select name="source_id">
                <option value="">—</option>
                <?php foreach ($sources as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($app['source_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <h3 style="font-size:0.9rem;margin:20px 0 12px">Application</h3>
    <div class="form-grid cols-3">
        <div class="form-group"><label>Job Role</label>
            <input value="<?= e($app['job_title']) ?>" disabled>
        </div>
        <div class="form-group"><label>Pipeline Stage</label>
            <select name="pipeline_id">
                <?php foreach ($stages as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($app['current_stage_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Status</label>
            <select name="status">
                <?php foreach (['pending','scheduled','processing','ongoing','incomplete','completed','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= ($app['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Decision</label>
            <select name="decision">
                <?php foreach (['none','advance','hold','reject','offer'] as $d): ?>
                <option value="<?= $d ?>" <?= ($app['decision'] ?? '') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Current CTC</label><input name="current_ctc" value="<?= e($app['current_ctc'] ?? '') ?>" placeholder="e.g. 18 LPA"></div>
        <div class="form-group"><label>Expected CTC</label><input name="expected_ctc" value="<?= e($app['expected_ctc'] ?? '') ?>" placeholder="e.g. 22 LPA"></div>
    </div>

    <h3 style="font-size:0.9rem;margin:20px 0 12px">Resume</h3>
    <?php if (!empty($app['resume_path'])): ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
        <a href="<?= resume_url($app['id']) ?>" class="btn btn-outline btn-sm">Download</a>
        <?php if ($canPreviewPdf): ?>
        <a href="<?= resume_url($app['id'], true) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Open in new tab</a>
        <?php else: ?>
        <span class="hint" style="margin:0">Inline preview is for PDF only (this file is <?= e($resumeExt ?: 'unknown') ?>).</span>
        <?php endif; ?>
    </div>
    <?php if ($canPreviewPdf): ?>
    <div class="resume-preview-panel" style="margin-bottom:12px">
        <object data="<?= resume_url($app['id'], true) ?>" type="application/pdf" style="width:100%;height:480px;border:1px solid var(--border);border-radius:8px;display:block">
            <iframe src="<?= resume_url($app['id'], true) ?>" title="Resume preview" style="width:100%;height:480px;border:0;border-radius:8px"></iframe>
        </object>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <p class="hint" style="margin-bottom:8px">No resume on file.</p>
    <?php endif; ?>
    <div class="form-group">
        <label><?= !empty($app['resume_path']) ? 'Replace resume' : 'Upload resume' ?></label>
        <input type="file" name="resume" accept=".pdf,.doc,.docx">
        <p class="hint">PDF, DOC, DOCX — max <?= (int) config('app')['resume_max_mb'] ?> MB<?php $phpMb = php_upload_limit_mb(); if ($phpMb > 0): ?> (PHP server limit: <?= $phpMb ?> MB)<?php endif; ?>. PDF can be previewed above.</p>
    </div>

    <div class="form-actions">
        <a href="<?= url('candidates') ?>" class="btn btn-outline">Back</a>
        <a href="<?= url('jobs/' . $app['job_id'] . '/interviews') ?>" class="btn btn-outline">Job Interviews</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>

<?php if ($canManage): ?>
<div class="card form-card" style="margin-top:16px">
    <h3 style="font-size:0.9rem;margin-bottom:12px">Move to another job</h3>
    <form method="POST" action="<?= url('applications/' . $app['id'] . '/move') ?>" class="form-grid cols-3">
        <?= csrf_field() ?>
        <div class="form-group"><label>Target job *</label>
            <select name="new_job_id" id="move_job_id" required onchange="loadMoveStages(this.value)">
                <option value="">Select job</option>
                <?php foreach ($jobs as $j): if ($j['id'] === $app['job_id']) continue; ?>
                <option value="<?= $j['id'] ?>"><?= e(job_select_option_label($j)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Pipeline stage *</label>
            <select name="new_pipeline_id" id="move_pipeline_id" required>
                <option value="">Select job first</option>
            </select>
        </div>
        <div class="form-group" style="align-self:end">
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:0.82rem" title="Leave unchecked to remove them from this job listing after the move">
                <input type="checkbox" name="keep_on_source_job" value="1">
                Also keep on this job (duplicate listing)
            </label>
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Move candidate to selected job?')">Move candidate</button>
        </div>
    </form>
</div>

<div class="card form-card" style="margin-top:16px;border-color:var(--gray-200)">
    <h3 style="font-size:0.9rem;margin-bottom:12px">Danger zone</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($archived): ?>
        <form method="POST" action="<?= url('applications/' . $app['id'] . '/restore') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline btn-sm">Restore from archive</button>
        </form>
        <?php else: ?>
        <form method="POST" action="<?= url('applications/' . $app['id'] . '/archive') ?>" onsubmit="return confirm('Archive this candidate for this job?')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline btn-sm">Archive</button>
        </form>
        <?php endif; ?>
        <form method="POST" action="<?= url('applications/' . $app['id'] . '/delete') ?>" onsubmit="return confirm('Permanently delete this application and all interviews?')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-reject btn-sm">Delete permanently</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($activity)): ?>
<div class="card form-card" style="margin-top:16px">
    <h3 style="font-size:0.9rem;margin-bottom:12px">Activity timeline</h3>
    <ul class="activity-timeline" style="list-style:none;margin:0;padding:0">
        <?php foreach ($activity as $ev): ?>
        <li style="padding:10px 0;border-bottom:1px solid var(--border);font-size:0.82rem">
            <strong><?= e(str_replace('_', ' ', $ev['action'])) ?></strong>
            <span style="color:var(--text-muted)"> · <?= e(format_date($ev['created_at'], 'd M Y H:i')) ?></span>
            <?php if (!empty($ev['first_name'])): ?>
            <span style="color:var(--text-muted)"> · <?= e($ev['first_name'] . ' ' . ($ev['last_name'] ?? '')) ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<script>
function loadMoveStages(jobId) {
    var sel = document.getElementById('move_pipeline_id');
    sel.innerHTML = '<option value="">Loading…</option>';
    if (!jobId) { sel.innerHTML = '<option value="">Select job first</option>'; return; }
    fetch('<?= url('api/jobs') ?>/' + jobId + '/stages')
        .then(function(r) { return r.json(); })
        .then(function(stages) {
            sel.innerHTML = '';
            stages.forEach(function(s) {
                var o = document.createElement('option');
                o.value = s.id; o.textContent = s.name;
                sel.appendChild(o);
            });
        });
}
</script>
