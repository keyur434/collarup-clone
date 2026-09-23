<?php
$isEdit = isset($mode) && $mode === 'edit';
$pageBack = ['url' => url('jobs'), 'label' => 'Job Roles'];
$aboutDefault = $job['about_company'] ?? ($company_about_default ?? '');
$cur = $job['salary_currency'] ?? 'INR';
$period = $job['salary_period'] ?? 'annual';
$selectedSkills = $job['skills_selected'] ?? [];
$extHm = $job['external_hm'] ?? [['name' => '', 'email' => '']];
$extIv = $job['external_iv'] ?? [];
if (empty($extHm)) {
    $extHm = [['name' => '', 'email' => '']];
}
?><div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Edit Job Role' : 'Create Job Role' ?></h1>
        <p>Define role details, compensation, and hiring team.</p>
    </div>
</div>
<?php include BASE_PATH . '/views/partials/wizard_steps.php'; ?>
<form method="POST" action="<?= $isEdit ? url('jobs/edit/' . $job['id']) : url('jobs/create') ?>" class="card form-card">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
        <div class="form-group"><label>Job Title *</label><input name="title" value="<?= e($job['title'] ?? '') ?>" required></div>
        <div class="form-group"><label>Department *</label>
            <select name="department_id" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= (($job['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Designation *</label>
            <select name="seniority_id" required>
                <option value="">Select designation</option>
                <?php foreach ($seniority as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (($job['seniority_id'] ?? '') == $s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Manage list under Settings → Designation Levels</p>
        </div>
    </div>
    <div class="form-grid cols-3">
        <div class="form-group"><label>Country *</label>
            <select name="country_id" id="country_id" required>
                <?php foreach ($countries as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($job['country_id'] ?? 1) == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>State *</label><select name="state_id" id="state_id" required></select></div>
        <div class="form-group"><label>City *</label><select name="city_id" id="city_id" required></select></div>
    </div>
    <div class="form-grid cols-2">
        <div class="form-group"><label>No. of Hires *</label><input type="number" name="num_hires" value="<?= e($job['num_hires'] ?? 1) ?>" min="1" required></div>
        <div class="form-group"><label>Work Mode *</label>
            <select name="work_mode_id" required>
                <?php foreach ($work_modes as $w): ?>
                <option value="<?= $w['id'] ?>" <?= (($job['work_mode_id'] ?? '') == $w['id']) ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-grid cols-2">
        <div class="form-group">
            <label>About Company *</label>
            <textarea name="about_company" id="about_company" rows="5" required><?= e($aboutDefault) ?></textarea>
            <?php if (!empty($company_about_default) && empty($job['about_company'])): ?>
            <p class="hint">Pre-filled from Settings → Integrations → Company profile</p>
            <?php endif; ?>
        </div>
        <div class="form-group"><label>Key Responsibility *</label><textarea name="key_responsibility" rows="5" required><?= e($job['key_responsibility'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Qualifications *</label><textarea name="qualifications" rows="5" required><?= e($job['qualifications'] ?? '') ?></textarea></div>
        <div class="form-group"><label>'Must Have' Criteria</label><textarea name="must_have_criteria" rows="5"><?= e($job['must_have_criteria'] ?? '') ?></textarea></div>
    </div>
    <div class="form-group">
        <label>Key Skills *</label>
        <?php if (!empty($skills_pool)): ?>
        <div class="skills-pool" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;max-height:160px;overflow:auto;padding:8px;border:1px solid var(--border);border-radius:8px">
            <?php foreach ($skills_pool as $sk): ?>
            <label class="skill-chip" style="display:inline-flex;align-items:center;gap:4px;font-size:0.85rem;cursor:pointer">
                <input type="checkbox" name="key_skills_selected[]" value="<?= e($sk['name']) ?>" <?= in_array($sk['name'], $selectedSkills, true) ? 'checked' : '' ?>>
                <?= e($sk['name']) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <input name="key_skills" value="<?= e($job['skills_str'] ?? '') ?>" placeholder="Additional skills (comma separated)">
        <p class="hint">Bulk pool from Settings → Skill Pool</p>
    </div>
    <div class="form-grid cols-4">
        <div class="form-group"><label>Currency</label>
            <select name="salary_currency">
                <option value="INR" <?= $cur === 'INR' ? 'selected' : '' ?>>₹ INR</option>
                <option value="USD" <?= $cur === 'USD' ? 'selected' : '' ?>>$ USD</option>
            </select>
        </div>
        <div class="form-group"><label>Min (annual)</label><input type="number" name="salary_min" step="50000" min="0" value="<?= e($job['salary_min'] ?? '') ?>" placeholder="e.g. 800000"></div>
        <div class="form-group"><label>Max (annual)</label><input type="number" name="salary_max" step="50000" min="0" value="<?= e($job['salary_max'] ?? '') ?>" placeholder="e.g. 1200000"></div>
        <div class="form-group"><label>Period</label>
            <select name="salary_period">
                <option value="annual" <?= $period === 'annual' ? 'selected' : '' ?>>Annual</option>
                <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
            </select>
        </div>
    </div>
    <div class="form-group"><label><input type="checkbox" name="hide_salary" <?= !empty($job['hide_salary']) ? 'checked' : '' ?>> Hide Salary?</label></div>
    <div class="form-grid cols-2">
        <div class="form-group"><label>Hiring Manager (team)</label>
            <select name="hiring_managers[]" multiple>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= in_array($u['id'], array_column($job['hiring_managers'] ?? [], 'user_id')) ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Interviewer(s) (team)</label>
            <select name="interviewers[]" multiple>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= in_array($u['id'], array_column($job['interviewers'] ?? [], 'user_id')) ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-grid cols-2">
        <div class="form-group">
            <label>External hiring managers</label>
            <div id="ext-hm-list">
                <?php foreach ($extHm as $i => $row): ?>
                <div class="form-grid cols-2" style="margin-bottom:6px">
                    <input name="external_hm_name[]" placeholder="Name" value="<?= e($row['name']) ?>">
                    <input name="external_hm_email[]" type="email" placeholder="Email (optional)" value="<?= e($row['email'] ?? '') ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="addExternalRow('ext-hm-list','external_hm')">+ Add</button>
        </div>
        <div class="form-group">
            <label>External interviewers</label>
            <div id="ext-iv-list">
                <?php if (empty($extIv)): ?>
                <div class="form-grid cols-2" style="margin-bottom:6px">
                    <input name="external_iv_name[]" placeholder="Name">
                    <input name="external_iv_email[]" type="email" placeholder="Email (optional)">
                </div>
                <?php else: foreach ($extIv as $row): ?>
                <div class="form-grid cols-2" style="margin-bottom:6px">
                    <input name="external_iv_name[]" placeholder="Name" value="<?= e($row['name']) ?>">
                    <input name="external_iv_email[]" type="email" placeholder="Email (optional)" value="<?= e($row['email'] ?? '') ?>">
                </div>
                <?php endforeach; endif; ?>
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="addExternalRow('ext-iv-list','external_iv')">+ Add</button>
        </div>
    </div>
    <p class="hint">At least one hiring manager required (team or external).</p>
    <div class="form-actions">
        <a href="<?= url('jobs') ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Next</button>
    </div>
</form>
<script>
function addExternalRow(containerId, prefix) {
    var wrap = document.getElementById(containerId);
    var row = document.createElement('div');
    row.className = 'form-grid cols-2';
    row.style.marginBottom = '6px';
    row.innerHTML = '<input name="' + prefix + '_name[]" placeholder="Name">'
        + '<input name="' + prefix + '_email[]" type="email" placeholder="Email (optional)">';
    wrap.appendChild(row);
}
document.addEventListener('DOMContentLoaded', function() {
    initLocationSelects(<?= json_encode(['country' => $job['country_id'] ?? 1, 'state' => $job['state_id'] ?? '', 'city' => $job['city_id'] ?? '']) ?>);
});
</script>
