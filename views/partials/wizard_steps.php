<?php
$steps = ($mode ?? 'create') === 'edit'
    ? ['Edit Job Role', 'Edit Pipeline', 'Edit Interview Question', 'Edit Draft Message']
    : ['Add New Job Role', 'Pipeline', 'Interview Question', 'Draft Message', 'Add Candidate(s)'];
$current = $step ?? 1;
?>
<div class="wizard-steps">
    <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
    <div class="wizard-step <?= $n < $current ? 'done' : ($n === $current ? 'active' : '') ?>">
        <span class="step-num"><?= $n <= $current && $n < $current ? '✓' : $n ?></span>
        <span class="step-label"><?= e($label) ?></span>
    </div>
    <?php endforeach; ?>
</div>
