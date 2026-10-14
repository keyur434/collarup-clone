<?php
$deskConfig = $deskConfig ?? HiringDeskConfigService::get($job['id']);
$weights = $deskConfig['weights'];
$minimums = $deskConfig['minimums'];
$rubric = $rubricCriteria ?? [];
?>
<section class="hd-insights-panel" id="hdScoringConfig">
    <div class="hd-insights-head">
        <div>
            <h2>Scoring bar &amp; weights (preview)</h2>
            <p class="text-muted" style="margin:4px 0 0;font-size:0.85rem">Assist composite on the table below — does not change interview AI scores.</p>
        </div>
    </div>
    <form method="POST" action="<?= url('hiring-desk/jobs/' . $job['id'] . '/config') ?>" class="hd-config-form">
        <?= csrf_field() ?>
        <div class="hd-config-grid">
            <?php foreach (['overall' => 'Overall min', 'experience' => 'Experience min', 'culture' => 'Culture min', 'soft_skills' => 'Soft skills min'] as $key => $label): ?>
            <label>
                <span><?= e($label) ?></span>
                <input type="number" step="0.1" min="0" max="10" name="minimums[<?= e($key) ?>]" value="<?= e($minimums[$key] ?? '') ?>" placeholder="—">
            </label>
            <?php endforeach; ?>
        </div>
        <div class="hd-config-grid" style="margin-top:12px">
            <?php foreach (['overall' => 'Overall wt%', 'experience' => 'Experience wt%', 'culture' => 'Culture wt%', 'soft_skills' => 'Soft wt%'] as $key => $label): ?>
            <label>
                <span><?= e($label) ?></span>
                <input type="number" step="1" min="0" max="100" name="weights[<?= e($key) ?>]" value="<?= e((int) ($weights[$key] ?? 0)) ?>">
            </label>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($rubric)): ?>
        <p style="font-size:0.85rem;margin:12px 0 6px;font-weight:600">Rubric criterion weights (%)</p>
        <div class="hd-config-grid">
            <?php foreach ($rubric as $cr):
                $n = $cr['name'];
                $wv = isset($weights['criteria'][$n]) ? (int) $weights['criteria'][$n] : 0;
            ?>
            <label>
                <span><?= e($n) ?></span>
                <input type="number" step="1" min="0" max="100" name="weights_criteria[<?= e($n) ?>]" value="<?= e($wv) ?>">
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:12px">Save scoring setup</button>
    </form>
</section>
