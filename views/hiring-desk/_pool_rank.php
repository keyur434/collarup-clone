<?php $poolRank = $poolRank ?? null; ?>
<section class="hd-insights-panel" id="hdPoolRank">
    <div class="hd-insights-head">
        <div>
            <h2>AI pool ranking (preview)</h2>
            <p class="text-muted" style="margin:4px 0 0;font-size:0.85rem">AI listwise compare in batches of 6 (preview — order is approximate when the pool is large).</p>
        </div>
        <form method="POST" class="hd-ai-form" action="<?= url('hiring-desk/jobs/' . $job['id'] . '/pool-rank/generate') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline btn-sm" data-hd-loading-text="Ranking pool…" <?= ($distribution['count'] ?? 0) < 3 ? 'disabled title="Need at least 3 interview reports"' : '' ?>>Generate pool rank</button>
        </form>
    </div>
    <?php if (!$poolRank): ?>
    <p style="font-size:0.9rem;color:var(--text-muted)">Requires ≥3 candidates with reports.</p>
    <?php else: ?>
    <?php if (!empty($poolRank['narrative'])): ?><p><?= e($poolRank['narrative']) ?></p><?php endif; ?>
    <ol class="hd-pool-rank-list">
        <?php foreach ($poolRank['ranking'] ?? [] as $item): ?>
        <li>
            <strong>#<?= (int) ($item['pool_rank'] ?? $item['rank_in_batch'] ?? 0) ?></strong>
            <?= e($insightNameMap[$item['application_id'] ?? ''] ?? 'Candidate') ?>
            <?php if (!empty($item['headline'])): ?> — <?= e($item['headline']) ?><?php endif; ?>
            <?php if (!empty($item['why'])): ?><div style="color:var(--text-muted);font-size:0.85rem"><?= e($item['why']) ?></div><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
</section>
