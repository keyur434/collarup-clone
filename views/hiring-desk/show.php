<?php
$breadcrumbs = nav_trail_dashboard('Hiring desk (preview)', url('hiring-desk'), $job['title']);
function hd_decision_label($d) {
    $d = $d ?: 'none';
    return ucfirst($d);
}
?>

<div class="hiring-desk-beta">
    <?php include BASE_PATH . '/views/hiring-desk/_beta_banner.php'; ?>

    <div class="page-header" style="margin-top:16px;flex-wrap:wrap;gap:12px">
        <div>
            <h1><?= e($job['title']) ?></h1>
            <p class="text-muted" style="margin:4px 0 0;font-size:0.9rem"><?= e($job['department_name'] ?? '') ?> · Assist ranking uses latest interview report only (for review, not auto-hire).</p>
        </div>
        <a href="<?= url('hiring-desk') ?>" class="btn btn-outline btn-sm">All job roles</a>
    </div>

    <?php if (($distribution['count'] ?? 0) > 0): ?>
    <div class="hd-stats-row">
        <div class="hd-stat-card">
            <span class="hd-stat-label">Reports in pool</span>
            <span class="hd-stat-value"><?= (int) $distribution['count'] ?></span>
        </div>
        <div class="hd-stat-card">
            <span class="hd-stat-label">Average overall</span>
            <span class="hd-stat-value"><?= e($distribution['avg']) ?></span>
        </div>
        <div class="hd-stat-card">
            <span class="hd-stat-label">Range</span>
            <span class="hd-stat-value"><?= e($distribution['min']) ?> – <?= e($distribution['max']) ?></span>
        </div>
        <div class="hd-stat-card">
            <span class="hd-stat-label">Middle 50% (approx.)</span>
            <span class="hd-stat-value"><?= e($distribution['q1']) ?> – <?= e($distribution['q3']) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php include BASE_PATH . '/views/hiring-desk/_insights.php'; ?>
    <?php include BASE_PATH . '/views/hiring-desk/_config.php'; ?>
    <?php include BASE_PATH . '/views/hiring-desk/_pool_rank.php'; ?>

    <div class="toolbar" style="margin-bottom:12px">
        <form method="GET" class="hd-toolbar-form">
            <select name="decision" class="filter-select" onchange="this.form.submit()">
                <option value="">All decisions</option>
                <?php foreach (['none', 'advance', 'hold', 'reject', 'offer'] as $d): ?>
                <option value="<?= $d ?>" <?= ($filters['decision'] ?? '') === $d ? 'selected' : '' ?>><?= hd_decision_label($d) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="filter-select" style="cursor:pointer">
                <input type="checkbox" name="has_report" value="1" <?= !empty($filters['has_report']) ? 'checked' : '' ?> onchange="this.form.submit()">
                Has AI report only
            </label>
        </form>
    </div>

    <form method="GET" action="<?= url('hiring-desk/jobs/' . $job['id'] . '/compare') ?>" id="hdPoolTable">
        <p class="hint" style="margin:0 0 10px">Pool table: interview scores come from completed AI reports. <strong>Resume vs JD</strong> is filled after you run <em>Score resumes vs JD</em> above (requires resume on file).</p>
        <div class="table-wrap">
            <div class="table-scroll">
                <table class="data-table hd-desk-table">
                    <thead>
                        <tr>
                            <th style="width:40px"><span class="sr-only">Select</span></th>
                            <th title="By Assist score when set, else interview overall">Pool rank</th>
                            <th>Candidate</th>
                            <th>Stage</th>
                            <th>Decision</th>
                            <th>Assist</th>
                            <th>Resume vs JD</th>
                            <th>Overall</th>
                            <th>Experience</th>
                            <th>Culture</th>
                            <th>Soft skills</th>
                            <th>Report</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applicants)): ?>
                        <tr><td colspan="12" class="empty-state">No applicants match these filters.</td></tr>
                        <?php else: foreach ($applicants as $a): ?>
                        <tr>
                            <td>
                                <?php if (!empty($a['report_id'])): ?>
                                <input type="checkbox" name="application_ids[]" value="<?= e($a['application_id']) ?>">
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($a['pool_rank']) ? (int) $a['pool_rank'] : '—' ?></td>
                            <td>
                                <div style="font-weight:600"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></div>
                                <div style="font-size:0.78rem;color:var(--text-muted)"><?= e($a['email']) ?></div>
                            </td>
                            <td><?= e($a['stage_name'] ?? '—') ?></td>
                            <td><?= e(hd_decision_label($a['decision'])) ?></td>
                            <td>
                                <?php if ($a['assist_composite'] !== null): ?>
                                <?= e($a['assist_composite']) ?>
                                <?php if (empty($a['passes_bar'])): ?>
                                <span class="badge badge-amber" title="Below minimum: <?= e(implode(', ', $a['bar_failures'] ?? [])) ?>">!</span>
                                <?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td data-label="Resume vs JD">
                                <?php
                                $rmGrade = $a['match_score_grade'] ?? null;
                                $rmScore = $a['resume_match_score'] ?? null;
                                $rmTip = $a['resume_match_summary'] ?? '';
                                if (!empty($a['resume_match_gaps'])) {
                                    $rmTip .= ($rmTip ? ' Gaps: ' : 'Gaps: ') . implode('; ', $a['resume_match_gaps']);
                                }
                                ?>
                                <?php if ($rmGrade): ?>
                                <span class="badge badge-<?= $rmGrade === 'A' ? 'completed' : ($rmGrade === 'B' ? 'amber' : 'gray') ?>" title="<?= e($rmTip) ?>"><?= e($rmGrade) ?></span>
                                <?php if ($rmScore !== null && $rmScore !== ''): ?>
                                <span style="font-size:0.78rem;color:var(--text-muted);margin-left:4px" title="<?= e($rmTip) ?>"><?= e($rmScore) ?>/10</span>
                                <?php endif; ?>
                                <?php elseif ($rmScore !== null && $rmScore !== ''): ?>
                                <span title="<?= e($rmTip) ?>"><?= e($rmScore) ?>/10</span>
                                <?php else: ?>
                                <span style="color:var(--text-muted);font-size:0.82rem" title="Upload a resume and run Score resumes vs JD">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $a['overall_score'] !== null ? e($a['overall_score']) : '—' ?></td>
                            <td><?= $a['experience_score'] !== null ? e($a['experience_score']) : '—' ?></td>
                            <td><?= $a['culture_score'] !== null ? e($a['culture_score']) : '—' ?></td>
                            <td><?= $a['soft_skills_score'] !== null ? e($a['soft_skills_score']) : '—' ?></td>
                            <td>
                                <?php if (!empty($a['report_id'])): ?>
                                <a href="<?= url('jobs/' . $job['id'] . '/reports/' . $a['report_id']) ?>" class="btn btn-outline btn-sm">View report</a>
                                <?php else: ?>
                                <span style="color:var(--text-muted);font-size:0.85rem">No report</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($applicants)): ?>
            <div class="table-footer">
                <p style="font-size:0.85rem;color:var(--text-muted);margin:0">Select 2–8 candidates with reports, then compare in preview.</p>
                <button type="submit" class="btn btn-primary btn-sm">Compare selected (preview)</button>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <?php include BASE_PATH . '/views/hiring-desk/_decision_log.php'; ?>

    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:12px">
        Standard tools: <a href="<?= url('jobs/' . $job['id'] . '/interviews') ?>">Job interviews</a> ·
        <a href="<?= url('leaderboard?jobId=' . $job['id']) ?>">Leaderboard</a> ·
        <a href="<?= url('candidates?job_id=' . $job['id']) ?>">Candidates</a>
    </p>
</div>
