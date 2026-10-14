<?php
/** @var array $job */
/** @var string $activeTab interviews|applicant-pool */
$activeTab = $activeTab ?? 'interviews';
$jobId = $job['id'];
?>
<div class="job-subtabs">
    <a href="<?= url('jobs/' . $jobId . '/interviews?tab=applicant-pool') ?>"
       class="job-subtab <?= $activeTab === 'applicant-pool' ? 'is-active' : '' ?>">Applicant Pool</a>
    <a href="<?= url('jobs/' . $jobId . '/interviews') ?>"
       class="job-subtab <?= $activeTab === 'interviews' ? 'is-active' : '' ?>">Interviews</a>
</div>
