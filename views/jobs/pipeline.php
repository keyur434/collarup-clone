<?php $pageBack = ['url' => url('jobs'), 'label' => 'Job Roles']; ?>
<div class="page-header">
    <div>
        <h1><?= ($mode ?? 'create') === 'edit' ? 'Edit Pipeline' : 'Pipeline Stages' ?></h1>
        <p>Configure interview rounds for this role.</p>
    </div>
</div>
<?php include BASE_PATH . '/views/partials/wizard_steps.php'; ?><form method="POST" action="<?= ($mode ?? 'create') === 'edit' ? url('jobs/edit/' . $job['id'] . '/pipeline') : url('jobs/create/pipeline') ?>" class="card form-card">
    <?= csrf_field() ?>
    <div id="pipeline-stages">
        <?php foreach ($stages as $idx => $stage): ?>
        <div class="pipeline-stage-row">
            <?php if (!$stage['is_system']): ?>
            <input type="hidden" name="stages[<?= $idx ?>][id]" value="<?= e($stage['id']) ?>">
            <input type="text" name="stages[<?= $idx ?>][name]" value="<?= e($stage['name']) ?>" class="stage-input">
            <?php else: ?>
            <div class="stage-system"><?= e($stage['name']) ?> <small>(system)</small></div>
            <input type="hidden" name="stages[<?= $idx ?>][id]" value="<?= e($stage['id']) ?>">
            <input type="hidden" name="stages[<?= $idx ?>][name]" value="<?= e($stage['name']) ?>">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="form-group">
        <label>Add Round Name</label>
        <input type="text" name="new_stages[]" placeholder="Round name">
    </div>
    <div class="form-actions">
        <a href="<?= url('jobs') ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Next</button>
    </div>
</form>
