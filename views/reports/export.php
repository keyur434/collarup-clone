<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report — <?= e($report['first_name'] . ' ' . $report['last_name']) ?></title>
    <style>
        body { font-family: Inter, Arial, sans-serif; color: #111; margin: 24px; line-height: 1.5; }
        h1 { font-size: 1.4rem; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 20px; }
        .score { font-size: 1.2rem; font-weight: 700; margin: 12px 0; }
        section { margin-bottom: 20px; page-break-inside: avoid; }
        h2 { font-size: 1rem; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        .transcript { white-space: pre-wrap; font-size: 0.85rem; background: #f8fafc; padding: 12px; border-radius: 6px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<button class="no-print" onclick="window.print()" style="margin-bottom:16px;padding:8px 16px">Print / Save as PDF</button>

<h1><?= e($report['first_name'] . ' ' . $report['last_name']) ?></h1>
<div class="meta"><?= e($report['job_title']) ?> · <?= e($report['department_name'] ?? '') ?> · <?= e(format_date($report['created_at'] ?? date('Y-m-d'), 'd M Y')) ?></div>
<div class="score">Overall: <?= e($report['overall_score']) ?> — <?= e($report['score_label']) ?></div>

<section>
    <h2>Scores</h2>
    <table>
        <tr><th>Dimension</th><th>Score</th></tr>
        <tr><td>Experience</td><td><?= e($report['experience_score'] ?? '—') ?></td></tr>
        <tr><td>Culture</td><td><?= e($report['culture_score'] ?? '—') ?></td></tr>
        <tr><td>Soft skills</td><td><?= e($report['soft_skills_score'] ?? '—') ?></td></tr>
        <?php foreach ($criterionScores as $cs): ?>
        <tr><td><?= e($cs['criterion_name']) ?></td><td><?= e($cs['score']) ?></td></tr>
        <?php endforeach; ?>
    </table>
</section>

<section>
    <h2>Overview</h2>
    <p><strong>Strengths:</strong> <?= e($report['overview_strengths']) ?></p>
    <p><strong>Weaknesses:</strong> <?= e($report['overview_weaknesses']) ?></p>
    <p><strong>Red flags:</strong> <?= e($report['overview_red_flags']) ?></p>
    <p><strong>Recommendation:</strong> <?= e($report['overview_recommendation']) ?></p>
</section>

<?php if (!empty($qaNotes)): ?>
<section>
    <h2>Q&amp;A Notes</h2>
    <?php foreach ($qaNotes as $qa): ?>
    <p><strong><?= e($qa['question_text']) ?></strong><br><?= e($qa['answer_summary']) ?></p>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($transcript && !empty($transcript['full_text'])): ?>
<section>
    <h2>Transcript</h2>
    <div class="transcript"><?= e($transcript['full_text']) ?></div>
</section>
<?php endif; ?>

</body>
</html>
