<?php

$isEdit = ($mode ?? 'create') === 'edit';

$jobId = isset($job['id']) ? $job['id'] : '';

if (!$isEdit && $jobId === '' && !empty($_SESSION['wizard_job_id'])) {

    $jobId = $_SESSION['wizard_job_id'];

}

$steps = $isEdit

    ? [

        ['label' => 'Edit Job Role', 'url' => $jobId ? url('jobs/edit/' . $jobId) : ''],

        ['label' => 'Edit Pipeline', 'url' => $jobId ? url('jobs/edit/' . $jobId . '/pipeline') : ''],

        ['label' => 'Questions & Rubric', 'url' => $jobId ? url('jobs/edit/' . $jobId . '/custom-question') : ''],

        ['label' => 'Edit Draft Message', 'url' => $jobId ? url('jobs/edit/' . $jobId . '/draft-message') : ''],

    ]

    : [

        ['label' => 'Add New Job Role', 'url' => $jobId ? url('jobs/create') : ''],

        ['label' => 'Pipeline', 'url' => $jobId ? url('jobs/create/pipeline') : ''],

        ['label' => 'Interview Question', 'url' => $jobId ? url('jobs/create/questions') : ''],

        ['label' => 'Draft Message', 'url' => $jobId ? url('jobs/create/draft-message') : ''],

        ['label' => 'Add Candidate(s)', 'url' => $jobId ? url('jobs/create/candidates') : ''],

    ];

$current = $step ?? 1;

?>

<div class="wizard-steps">

    <?php foreach ($steps as $i => $stepDef): $n = $i + 1; ?>

    <div class="wizard-step <?= $n < $current ? 'done' : ($n === $current ? 'active' : '') ?>">

        <span class="step-num"><?= $n < $current ? '✓' : $n ?></span>

        <?php if (!empty($stepDef['url']) && ($isEdit || $n < $current)): ?>

        <a href="<?= e($stepDef['url']) ?>" class="step-label" style="color:inherit;text-decoration:none"><?= e($stepDef['label']) ?></a>

        <?php else: ?>

        <span class="step-label"><?= e($stepDef['label']) ?></span>

        <?php endif; ?>

    </div>

    <?php endforeach; ?>

</div>

