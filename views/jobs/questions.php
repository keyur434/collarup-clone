<?php $pageBack = ['url' => url('jobs'), 'label' => 'Job Roles']; ?>
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
    <div class="form-actions">
        <a href="<?= url('jobs') ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><?= ($mode ?? 'create') === 'edit' ? 'Next' : 'Next' ?></button>
        <?php if (($mode ?? 'create') === 'edit'): ?><button type="submit" name="skip" value="1" class="btn btn-outline">Skip</button><?php endif; ?>
    </div>
</form>
