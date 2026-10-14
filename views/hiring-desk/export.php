<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($title) ?></title>
    <style>
        body { font-family: Inter, Arial, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; }
        h2 { font-size: 14px; margin-top: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #666; font-size: 11px; }
    </style>
</head>
<body>
    <h1><?= e($job['title']) ?> — Hiring desk committee pack (preview)</h1>
    <p class="muted">Generated <?= e(date('d M Y H:i')) ?> · Assistive only — HR decides</p>

    <?php if (!empty($insightData['cohort_summary'])): ?>
    <h2>Cohort summary</h2>
    <p><?= e($insightData['cohort_summary']) ?></p>
    <?php endif; ?>

    <?php if (!empty($insightData['suggested_shortlist'])): ?>
    <h2>Suggested shortlist (review)</h2>
    <ol>
        <?php foreach ($insightData['suggested_shortlist'] as $s): ?>
        <li><?= e($insightNameMap[$s['application_id'] ?? ''] ?? 'Candidate') ?> — <?= e($s['headline'] ?? '') ?></li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <?php if (!empty($poolRank['ranking'])): ?>
    <h2>Pool ranking</h2>
    <ol>
        <?php foreach ($poolRank['ranking'] as $r): ?>
        <li>#<?= (int) ($r['pool_rank'] ?? 0) ?> <?= e($insightNameMap[$r['application_id'] ?? ''] ?? '') ?> — <?= e($r['why'] ?? '') ?></li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <h2>Applicant scores</h2>
    <table>
        <thead>
            <tr>
                <th>Candidate</th><th>Overall</th><th>Assist</th><th>CV</th><th>Decision</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($applicants as $a): ?>
            <tr>
                <td><?= e($a['first_name'] . ' ' . $a['last_name']) ?></td>
                <td><?= e($a['overall_score'] ?? '—') ?></td>
                <td><?= e($a['assist_composite'] ?? '—') ?></td>
                <td><?= e($a['match_score_grade'] ?? '—') ?></td>
                <td><?= e($a['decision'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($decisionNotes)): ?>
    <h2>HR notes</h2>
    <ul>
        <?php foreach ($decisionNotes as $n): ?>
        <li><?= e($n['cand_first'] . ' ' . $n['cand_last']) ?>: <?= e($n['reason']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</body>
</html>
