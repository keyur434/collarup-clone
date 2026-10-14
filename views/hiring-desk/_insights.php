<?php
$insightDisplay = $insightDisplay ?? ['row' => null, 'data' => null, 'stale' => false];
$insightRow = $insightDisplay['row'];
$insightData = $insightDisplay['data'];
$insightStale = !empty($insightDisplay['stale']);
$nameByApp = $insightNameMap ?? [];
function hd_app_name(array $map, $id) {
    return isset($map[$id]) ? $map[$id] : 'Candidate';
}
?>

<section class="hd-insights-panel" id="hdAiBrief">
    <div class="hd-insights-head">
        <div>
            <h2>AI hiring brief</h2>
            <p class="text-muted" style="margin:4px 0 0;font-size:0.85rem">Comparative insight across interview <strong>AI reports</strong> (one Azure call, cached). Separate from resume matching below.</p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <form method="POST" class="hd-ai-form" action="<?= url('hiring-desk/jobs/' . $job['id'] . '/insights/generate') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm" data-hd-loading-text="Generating brief…" <?= ($distribution['count'] ?? 0) < 2 ? 'disabled title="Need at least 2 reports"' : '' ?>>
                    <?= $insightData ? 'Regenerate brief' : 'Generate brief' ?>
                </button>
            </form>
            <form method="POST" class="hd-ai-form" action="<?= url('hiring-desk/jobs/' . $job['id'] . '/resume-match/score') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm" data-hd-loading-text="Scoring resumes…" title="Compare each uploaded CV to this job description">Score resumes vs JD</button>
            </form>
            <a href="<?= url('hiring-desk/jobs/' . $job['id'] . '/export') ?>" class="btn btn-outline btn-sm" target="_blank">Committee pack (HTML)</a>
        </div>
    </div>

    <div class="hd-insight-block" style="margin-top:12px;background:var(--gray-50,#f9fafb);padding:12px 14px;border-radius:8px">
        <h3 style="margin:0 0 6px;font-size:0.9rem">What “Score resumes vs JD” does</h3>
        <ul style="margin:0;padding-left:1.15rem;font-size:0.84rem;line-height:1.5;color:var(--text-muted)">
            <li>Reads each candidate’s <strong>uploaded resume</strong> (PDF/DOC) and compares it to <strong>this job’s</strong> title, skills, qualifications, must-haves, and key responsibilities — not interview recordings.</li>
            <li>Runs one Azure OpenAI call per resume (results cached in <code>cv_summaries</code> until the file changes).</li>
            <li>Writes a letter grade on each row in the table below: column <strong>Resume vs JD</strong> — <strong>A</strong> strong fit, <strong>B</strong> moderate, <strong>C</strong> weak. Hover the cell for a one-line summary.</li>
            <li>Does not change interview scores or pipeline stage. Candidates without a resume file are skipped.</li>
        </ul>
    </div>

    <?php if ($insightRow && ($insightRow['status'] ?? '') === 'failed'): ?>
    <div class="alert alert-error" role="alert">Last run failed: <?= e($insightRow['error_message'] ?? 'Unknown error') ?></div>
    <?php endif; ?>

    <?php if ($insightStale && $insightData): ?>
    <div class="alert" role="alert" style="background:var(--amber-50,#fffbeb);border:1px solid #f59e0b;color:#92400e">
        Job description, reports, or rubric changed since this brief was generated. Regenerate for up-to-date comparison.
    </div>
    <?php endif; ?>

    <?php if (!$insightData): ?>
    <p style="font-size:0.9rem;color:var(--text-muted);margin:12px 0 0">
        No brief yet. Requires ≥2 candidates with completed AI reports. Does not re-run interview analysis.
    </p>
    <?php else: ?>

    <?php if (!empty($insightData['disclaimer'])): ?>
    <p class="hd-insight-disclaimer"><?= e($insightData['disclaimer']) ?>
        <?php if (!empty($insightData['confidence'])): ?>
        <span class="badge badge-gray" style="margin-left:8px">Confidence: <?= e($insightData['confidence']) ?></span>
        <?php endif; ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($insightRow['updated_at'])): ?>
    <p style="font-size:0.78rem;color:var(--text-muted);margin:0 0 12px">
        Generated <?= format_date($insightRow['updated_at'], 'd M Y, H:i') ?>
        <?php if (!empty($insightRow['model_name'])): ?> · <?= e($insightRow['model_name']) ?><?php endif; ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($insightData['cohort_summary'])): ?>
    <div class="hd-insight-block">
        <h3>Cohort summary</h3>
        <p><?= nl2br(e($insightData['cohort_summary'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($insightData['pool_interpretation'])): ?>
    <div class="hd-insight-block">
        <h3>How to read this pool</h3>
        <p><?= nl2br(e($insightData['pool_interpretation'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($insightData['suggested_shortlist']) && is_array($insightData['suggested_shortlist'])): ?>
    <div class="hd-insight-block">
        <h3>Suggested shortlist (for review)</h3>
        <div class="hd-shortlist">
            <?php foreach ($insightData['suggested_shortlist'] as $item): ?>
            <div class="hd-shortlist-card">
                <div class="hd-shortlist-title">
                    <span class="hd-rank">#<?= (int) ($item['rank'] ?? 0) ?></span>
                    <?= e(hd_app_name($nameByApp, $item['application_id'] ?? '')) ?>
                    <?php if (!empty($item['headline'])): ?> — <?= e($item['headline']) ?><?php endif; ?>
                </div>
                <?php if (!empty($item['why_consider'])): ?>
                <p><strong>Why consider</strong></p>
                <ul><?php foreach ($item['why_consider'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($item['risks_or_gaps'])): ?>
                <p><strong>Risks / gaps</strong></p>
                <ul><?php foreach ($item['risks_or_gaps'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($item['report_id'])): ?>
                <a href="<?= url('jobs/' . $job['id'] . '/reports/' . $item['report_id']) ?>" class="btn btn-outline btn-sm">View report</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($insightData['head_to_head']) && is_array($insightData['head_to_head'])): ?>
    <div class="hd-insight-block">
        <h3>Head-to-head</h3>
        <?php foreach ($insightData['head_to_head'] as $h2h): ?>
        <div class="hd-h2h-card">
            <p><strong><?= e(hd_app_name($nameByApp, $h2h['application_id_a'] ?? '')) ?></strong> vs <strong><?= e(hd_app_name($nameByApp, $h2h['application_id_b'] ?? '')) ?></strong></p>
            <?php if (!empty($h2h['summary'])): ?><p><?= e($h2h['summary']) ?></p><?php endif; ?>
            <?php if (!empty($h2h['tradeoffs'])): ?>
            <ul><?php foreach ($h2h['tradeoffs'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($insightData['committee_questions'])): ?>
    <div class="hd-insight-block">
        <h3>Questions for the committee</h3>
        <ul><?php foreach ($insightData['committee_questions'] as $q): ?><li><?= e($q) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($insightData['coverage_gaps'])): ?>
    <div class="hd-insight-block">
        <h3>Coverage gaps (pool-wide)</h3>
        <ul><?php foreach ($insightData['coverage_gaps'] as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($insightData['integrity_or_risk_notes'])): ?>
    <div class="hd-insight-block">
        <h3>Risks to judge</h3>
        <ul><?php foreach ($insightData['integrity_or_risk_notes'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</section>
