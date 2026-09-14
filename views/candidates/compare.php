<?php
$pageBack = ['url' => url('candidates?job_id=' . $job['id']), 'label' => 'Candidates'];
$count = count($candidates);
?>

<div class="page-header">
    <div>
        <h1>Compare Candidates</h1>
        <p><?= e($job['title']) ?> — <?= (int) $count ?> selected</p>
    </div>
</div>

<div class="table-wrap" style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Metric</th>
                <?php foreach ($candidates as $c): ?>
                <th><?= e($c['first_name'] . ' ' . $c['last_name']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Overall score</strong></td>
                <?php foreach ($candidates as $c): ?>
                <td><?= $c['overall_score'] !== null ? e($c['overall_score']) . ' (' . e($c['score_label']) . ')' : '—' ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Experience</td>
                <?php foreach ($candidates as $c): ?>
                <td><?= $c['experience_score'] !== null ? e($c['experience_score']) : '—' ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Culture fit</td>
                <?php foreach ($candidates as $c): ?>
                <td><?= $c['culture_score'] !== null ? e($c['culture_score']) : '—' ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Soft skills</td>
                <?php foreach ($candidates as $c): ?>
                <td><?= $c['soft_skills_score'] !== null ? e($c['soft_skills_score']) : '—' ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Years experience</td>
                <?php foreach ($candidates as $c): ?>
                <td><?= $c['years_experience'] !== null ? e($c['years_experience']) : '—' ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Stage</td>
                <?php foreach ($candidates as $c): ?>
                <td><?= e($c['stage_name'] ?? '—') ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Decision</td>
                <?php foreach ($candidates as $c): ?>
                <td><?= e(ucfirst($c['decision'] ?? 'none')) ?></td>
                <?php endforeach; ?>
            </tr>
            <?php
            $allCriteria = [];
            foreach ($candidates as $c) {
                foreach ($c['criterion_scores'] as $cs) {
                    $allCriteria[$cs['criterion_name']] = true;
                }
            }
            foreach (array_keys($allCriteria) as $criterionName):
            ?>
            <tr>
                <td><?= e($criterionName) ?></td>
                <?php foreach ($candidates as $c): ?>
                <td><?php
                    $score = '—';
                    foreach ($c['criterion_scores'] as $cs) {
                        if ($cs['criterion_name'] === $criterionName) {
                            $score = $cs['score'];
                            break;
                        }
                    }
                    echo e($score);
                ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td><strong>Strengths</strong></td>
                <?php foreach ($candidates as $c): ?>
                <td style="font-size:0.82rem;max-width:220px"><?= e($c['overview_strengths'] ?? '—') ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td><strong>Weaknesses</strong></td>
                <?php foreach ($candidates as $c): ?>
                <td style="font-size:0.82rem;max-width:220px"><?= e($c['overview_weaknesses'] ?? '—') ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Recommendation</td>
                <?php foreach ($candidates as $c): ?>
                <td style="font-size:0.82rem"><?= e($c['overview_recommendation'] ?? '—') ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Report</td>
                <?php foreach ($candidates as $c): ?>
                <td>
                    <?php if (!empty($c['report_id'])): ?>
                    <a href="<?= url('jobs/' . $job['id'] . '/reports/' . $c['report_id']) ?>">View</a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
</div>
