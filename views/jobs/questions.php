<?php
$isEdit = ($mode ?? 'create') === 'edit';
$crumbLeaf = $isEdit ? 'Edit Questions' : 'Interview Questions';
$breadcrumbs = !empty($job['id'])
    ? nav_trail_job($job, $crumbLeaf)
    : nav_trail_dashboard('Job Roles', url('jobs'), $crumbLeaf);
?>
<div class="page-header">
    <div>
        <h1><?= ($mode ?? 'create') === 'edit' ? 'Edit Questions' : 'Interview Questions' ?></h1>
        <p>Custom questions used during AI analysis.</p>
    </div>
</div>
<?php include BASE_PATH . '/views/partials/wizard_steps.php'; ?><form method="POST" action="<?= ($mode ?? 'create') === 'edit' ? url('jobs/edit/' . $job['id'] . '/custom-question') : url('jobs/create/questions') ?>" class="card form-card">
    <?= csrf_field() ?>
    <div id="questions-list">
        <?php if (!empty($questions)): foreach ($questions as $q): ?>
        <div class="form-group"><textarea name="questions[]" rows="3" placeholder="Interview question"><?= e($q['question_text']) ?></textarea></div>
        <?php endforeach; else: ?>
        <div class="form-group"><textarea name="questions[]" rows="3" placeholder='Example: "Can you describe your experience working with [specific tech]?"'></textarea></div>
        <?php endif; ?>
    </div>
    <button type="button" class="btn btn-outline" onclick="addQuestion()">+ Add Question</button>

    <?php if (($mode ?? 'create') === 'edit'): ?>
    <h3 style="margin:24px 0 12px;font-size:0.95rem">AI scoring rubric</h3>
    <p class="hint" style="margin-bottom:12px">Criteria used for interview scorecards and AI analysis.</p>
    <div id="rubric-list">
        <?php if (!empty($rubric)): foreach ($rubric as $rc): if (empty($rc['is_active'])) continue; ?>
        <div class="form-grid cols-2" style="margin-bottom:10px">
            <input type="hidden" name="rubric_id[]" value="<?= (int) $rc['id'] ?>">
            <div class="form-group" style="margin:0"><input name="rubric_name[]" value="<?= e($rc['name']) ?>" placeholder="Criterion name" required></div>
            <div class="form-group" style="margin:0"><input name="rubric_description[]" value="<?= e($rc['description'] ?? '') ?>" placeholder="What to evaluate (optional)"></div>
        </div>
        <?php endforeach; else: ?>
        <div class="form-grid cols-2" style="margin-bottom:10px">
            <input type="hidden" name="rubric_id[]" value="0">
            <div class="form-group" style="margin:0"><input name="rubric_name[]" placeholder="Criterion name"></div>
            <div class="form-group" style="margin:0"><input name="rubric_description[]" placeholder="Description"></div>
        </div>
        <?php endif; ?>
    </div>
    <button type="button" class="btn btn-outline btn-sm" onclick="addRubricRow()">+ Add criterion</button>
    <?php endif; ?>

    <div class="form-actions">
        <a href="<?= url('jobs') ?>" class="btn btn-outline">Cancel</a>
        <?php if (($mode ?? 'create') === 'create'): ?>
        <a href="<?= url('jobs/create/pipeline') ?>" class="btn btn-outline">Back</a>
        <?php else: ?>
        <a href="<?= url('jobs/edit/' . $job['id'] . '/pipeline') ?>" class="btn btn-outline">Back</a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Next</button>
        <?php if (($mode ?? 'create') === 'create'): ?>
        <button type="submit" name="skip_questions" value="1" class="btn btn-outline">Skip (optional)</button>
        <?php elseif (($mode ?? 'create') === 'edit'): ?>
        <button type="submit" name="skip" value="1" class="btn btn-outline">Skip</button>
        <?php endif; ?>
    </div>
</form>
<?php if (($mode ?? 'create') === 'edit'): ?>
<script>
function addRubricRow() {
    var wrap = document.getElementById('rubric-list');
    var row = document.createElement('div');
    row.className = 'form-grid cols-2';
    row.style.marginBottom = '10px';
    row.innerHTML = '<input type="hidden" name="rubric_id[]" value="0">'
        + '<div class="form-group" style="margin:0"><input name="rubric_name[]" placeholder="Criterion name"></div>'
        + '<div class="form-group" style="margin:0"><input name="rubric_description[]" placeholder="Description"></div>';
    wrap.appendChild(row);
}
</script>
<?php endif; ?>
