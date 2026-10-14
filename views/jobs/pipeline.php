<?php
$isEdit = ($mode ?? 'create') === 'edit';
$crumbLeaf = $isEdit ? 'Edit Pipeline' : 'Pipeline Stages';
$breadcrumbs = !empty($job['id'])
    ? nav_trail_job($job, $crumbLeaf)
    : nav_trail_dashboard('Job Roles', url('jobs'), $crumbLeaf);
?>
<div class="page-header">
    <div>
        <h1><?= ($mode ?? 'create') === 'edit' ? 'Edit Pipeline' : 'Pipeline Stages' ?></h1>
        <p>Reorder or remove interview rounds. Applicant Pool and Offer stay fixed.</p>
    </div>
</div>
<?php include BASE_PATH . '/views/partials/wizard_steps.php'; ?>
<form method="POST" action="<?= ($mode ?? 'create') === 'edit' ? url('jobs/edit/' . $job['id'] . '/pipeline') : url('jobs/create/pipeline') ?>" class="card form-card" id="pipelineForm">
    <?= csrf_field() ?>
    <div id="pipeline-stages">
        <?php foreach ($stages as $idx => $stage): ?>
        <div class="pipeline-stage-row" data-stage-id="<?= e($stage['id']) ?>" data-system="<?= (int) $stage['is_system'] ?>" style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding:8px;border:1px solid var(--border);border-radius:8px">
            <input type="hidden" name="stage_order[]" value="<?= e($stage['id']) ?>">
            <div style="display:flex;flex-direction:column;gap:2px">
                <button type="button" class="btn btn-outline btn-sm pipeline-move-up" title="Move up">↑</button>
                <button type="button" class="btn btn-outline btn-sm pipeline-move-down" title="Move down">↓</button>
            </div>
            <?php if (!$stage['is_system']): ?>
            <input type="hidden" name="stages[<?= $idx ?>][id]" value="<?= e($stage['id']) ?>">
            <input type="text" name="stages[<?= $idx ?>][name]" value="<?= e($stage['name']) ?>" class="stage-input" style="flex:1">
            <button type="button" class="btn btn-outline btn-sm pipeline-delete-round" title="Remove round">Delete</button>
            <?php else: ?>
            <div class="stage-system" style="flex:1"><?= e($stage['name']) ?> <small>(system)</small></div>
            <input type="hidden" name="stages[<?= $idx ?>][id]" value="<?= e($stage['id']) ?>">
            <input type="hidden" name="stages[<?= $idx ?>][name]" value="<?= e($stage['name']) ?>">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div id="pipeline-deleted-fields"></div>
    <div class="form-group">
        <label>Add Round Name</label>
        <input type="text" name="new_stages[]" placeholder="Round name">
    </div>
    <div class="form-actions">
        <a href="<?= url('jobs') ?>" class="btn btn-outline">Cancel</a>
        <?php if (($mode ?? 'create') === 'create'): ?>
        <a href="<?= url('jobs/create') ?>" class="btn btn-outline">Back</a>
        <?php else: ?>
        <a href="<?= url('jobs/edit/' . $job['id']) ?>" class="btn btn-outline">Back</a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Next</button>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() { initPipelineStageEditor('pipelineForm'); });
</script>
