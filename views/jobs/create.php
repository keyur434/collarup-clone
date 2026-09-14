<?php
$isEdit = isset($mode) && $mode === 'edit';
$pageBack = ['url' => url('jobs'), 'label' => 'Job Roles'];
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
        <div class="form-group"><label>Seniority *</label>
            <select name="seniority_id" required>
                <option value="">Select Seniority</option>
                <?php foreach ($seniority as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (($job['seniority_id'] ?? '') == $s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
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
        <div class="form-group"><label>About Company *</label><textarea name="about_company" rows="5" required><?= e($job['about_company'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Key Responsibility *</label><textarea name="key_responsibility" rows="5" required><?= e($job['key_responsibility'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Qualifications *</label><textarea name="qualifications" rows="5" required><?= e($job['qualifications'] ?? '') ?></textarea></div>
        <div class="form-group"><label>'Must Have' Criteria</label><textarea name="must_have_criteria" rows="5"><?= e($job['must_have_criteria'] ?? '') ?></textarea></div>
    </div>
    <div class="form-group"><label>Key Skills *</label><input name="key_skills" value="<?= e($job['skills_str'] ?? '') ?>" placeholder="Comma separated skills" required></div>
    <div class="form-grid cols-4">
        <div class="form-group"><label>Currency</label><select name="salary_currency"><option value="INR">₹</option><option value="USD">$</option></select></div>
        <div class="form-group"><label>Min</label><input type="number" name="salary_min" value="<?= e($job['salary_min'] ?? '') ?>"></div>
        <div class="form-group"><label>Max</label><input type="number" name="salary_max" value="<?= e($job['salary_max'] ?? '') ?>"></div>
        <div class="form-group"><label>Period</label><select name="salary_period"><option value="monthly">Monthly</option><option value="annual">Annual</option></select></div>
    </div>
    <div class="form-group"><label><input type="checkbox" name="hide_salary" <?= !empty($job['hide_salary']) ? 'checked' : '' ?>> Hide Salary?</label></div>
    <div class="form-grid cols-2">
        <div class="form-group"><label>Hiring Manager *</label>
            <select name="hiring_managers[]" multiple required>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= in_array($u['id'], array_column($job['hiring_managers'] ?? [], 'user_id')) ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Interviewer(s)</label>
            <select name="interviewers[]" multiple>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= in_array($u['id'], array_column($job['interviewers'] ?? [], 'user_id')) ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-actions">
        <a href="<?= url('jobs') ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Next' : 'Next' ?></button>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initLocationSelects(<?= json_encode(['country' => $job['country_id'] ?? 1, 'state' => $job['state_id'] ?? '', 'city' => $job['city_id'] ?? '']) ?>);
});
</script>
