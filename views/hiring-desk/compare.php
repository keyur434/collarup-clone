<?php
$breadcrumbs = nav_trail_dashboard(
    'Hiring desk (preview)',
    url('hiring-desk'),
    'Compare'
);
$count = count($candidates);
$metrics = [
    'overall_score' => 'Overall',
    'experience_score' => 'Experience',
    'culture_score' => 'Culture',
    'soft_skills_score' => 'Soft skills',
];
$allCriteria = [];
foreach ($candidates as $c) {
    foreach ($c['criterion_scores'] as $cs) {
        $allCriteria[$cs['criterion_name']] = true;
    }
}
ksort($allCriteria, SORT_NATURAL | SORT_FLAG_CASE);
?>

<div class="hiring-desk-beta">
    <?php include BASE_PATH . '/views/hiring-desk/_beta_banner.php'; ?>

    <div class="page-header" style="margin-top:16px">
        <div>
            <h1>Compare candidates (preview)</h1>
            <p class="text-muted" style="margin:4px 0 0"><?= e($job['title']) ?> — <?= (int) $count ?> shown (max 8)</p>
        </div>
        <a href="<?= url('hiring-desk/jobs/' . $job['id']) ?>" class="btn btn-outline btn-sm">Back to desk</a>
    </div>
    <?php if (!empty($compareTruncated)): ?>
    <p class="hint" style="margin:0 0 12px">Only the first 8 selected candidates are compared.</p>
    <?php endif; ?>

    <div class="table-wrap">
        <div class="table-scroll">
            <table class="data-table hd-compare-table">
                <thead>
                    <tr>
                        <th class="hd-metric-col">Metric</th>
                        <?php foreach ($candidates as $c): ?>
                        <th><?= e($c['first_name'] . ' ' . $c['last_name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metrics as $key => $label): ?>
                    <tr>
                        <td class="hd-metric-col"><strong><?= e($label) ?></strong></td>
                        <?php foreach ($candidates as $c):
                            $val = $c[$key] ?? null;
                            $cls = HiringDeskService::heatClass($val);
                        ?>
                        <td class="hd-heat-cell <?= e($cls) ?>"><?= $val !== null ? e($val) : '—' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach (array_keys($allCriteria) as $criterionName): ?>
                    <tr>
                        <td class="hd-metric-col"><?= e($criterionName) ?></td>
                        <?php foreach ($candidates as $c):
                            $val = null;
                            foreach ($c['criterion_scores'] as $cs) {
                                if ($cs['criterion_name'] === $criterionName) {
                                    $val = $cs['score'];
                                    break;
                                }
                            }
                            $cls = HiringDeskService::heatClass($val);
                        ?>
                        <td class="hd-heat-cell <?= e($cls) ?>"><?= $val !== null ? e($val) : '—' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td class="hd-metric-col">Stage</td>
                        <?php foreach ($candidates as $c): ?>
                        <td><?= e($c['stage_name'] ?? '—') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="hd-metric-col">Decision</td>
                        <?php foreach ($candidates as $c): ?>
                        <td><?= e(ucfirst($c['decision'] ?? 'none')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="hd-compare-notes" style="margin-top:20px">
        <h2 style="font-size:1rem;margin-bottom:12px">Summary snippets</h2>
        <div class="hd-notes-grid">
            <?php foreach ($candidates as $c): ?>
            <div class="hd-note-card">
                <h3><?= e($c['first_name'] . ' ' . $c['last_name']) ?></h3>
                <?php if (!empty($c['report_id'])): ?>
                <p><a href="<?= url('jobs/' . $job['id'] . '/reports/' . $c['report_id']) ?>">Open full report</a></p>
                <?php endif; ?>
                <?php if (!empty($c['overview_recommendation'])): ?>
                <p><strong>AI note</strong> <?= e(mb_strimwidth(strip_tags((string) $c['overview_recommendation']), 0, 400, '…')) ?></p>
                <?php endif; ?>
                <?php if (!empty($c['overview_strengths'])): ?>
                <p><strong>Strengths</strong> <?= e(mb_strimwidth(strip_tags((string) $c['overview_strengths']), 0, 280, '…')) ?></p>
                <?php endif; ?>
                <?php if (!empty($c['overview_weaknesses'])): ?>
                <p><strong>Gaps</strong> <?= e(mb_strimwidth(strip_tags((string) $c['overview_weaknesses']), 0, 280, '…')) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:16px">
        Legacy compare (unchanged): use Candidates list → select 2–4 → existing compare flow at
        <code style="font-size:0.75rem">/jobs/<?= e($job['id']) ?>/compare</code>.
    </p>
</div>
