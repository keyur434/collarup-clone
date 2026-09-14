<?php $pageBack = ['url' => url('jobs'), 'label' => 'Job Roles']; ?>
<div class="page-header">
    <div>
        <h1><?= ($mode ?? 'create') === 'edit' ? 'Edit Email Templates' : 'Draft Messages' ?></h1>
        <p>Invitation and rejection emails for candidates.</p>
    </div>
</div>
<?php include BASE_PATH . '/views/partials/wizard_steps.php'; ?><form method="POST" action="<?= ($mode ?? 'create') === 'edit' ? url('jobs/edit/' . $job['id'] . '/draft-message') : url('jobs/create/draft-message') ?>" class="card form-card">
    <?= csrf_field() ?>
    <h3>Email For Invited candidates</h3>
    <div class="email-editor-grid">
        <div class="form-group"><label>Template (Invited)</label><textarea name="invited" rows="15"><?= e($templates['invited']) ?></textarea></div>
        <div class="email-preview"><?= nl2br(e($templates['invited'])) ?></div>
    </div>
    <h3>Email For Rejected candidates</h3>
    <div class="email-editor-grid">
        <div class="form-group"><label>Template (Rejected)</label><textarea name="rejected" rows="10"><?= e($templates['rejected']) ?></textarea></div>
        <div class="email-preview"><?= nl2br(e($templates['rejected'])) ?></div>
    </div>
    <p class="hint">Placeholders: [Candidate Name], [Job Title], [Company Name], [Expiry Date], [Contact Email], [Hiring Manager Name]</p>
    <div class="form-actions">
        <a href="<?= url('jobs') ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><?= ($mode ?? 'create') === 'edit' ? 'Update' : 'Finish' ?></button>
    </div>
</form>
