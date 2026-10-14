<?php $decisionNotes = $decisionNotes ?? []; ?>
<section class="hd-insights-panel" id="hdDecisionLog">
    <h2 style="font-size:1.05rem;margin:0 0 12px">HR decision notes (preview audit)</h2>
    <form method="POST" action="<?= url('hiring-desk/jobs/' . $job['id'] . '/decision-note') ?>" class="hd-decision-form">
        <?= csrf_field() ?>
        <select name="application_id" class="filter-select" required>
            <option value="">Candidate</option>
            <?php foreach (($decisionLogApplicants ?? $applicants ?? []) as $a): ?>
            <option value="<?= e($a['application_id']) ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="note_type" class="filter-select">
            <option value="override_shortlist">Disagree with AI shortlist</option>
            <option value="agree_shortlist">Agree with AI shortlist</option>
            <option value="general">General note</option>
        </select>
        <input type="text" name="reason" class="filter-select" style="flex:1;min-width:200px" placeholder="Reason (required)" required maxlength="2000">
        <button type="submit" class="btn btn-outline btn-sm">Log note</button>
    </form>
    <?php if (!empty($decisionNotes)): ?>
    <ul class="hd-audit-list">
        <?php foreach ($decisionNotes as $n): ?>
        <li>
            <span style="font-size:0.78rem;color:var(--text-muted)"><?= format_date($n['created_at'], 'd M Y H:i') ?> · <?= e($n['first_name'] . ' ' . $n['last_name']) ?></span><br>
            <strong><?= e($n['cand_first'] . ' ' . $n['cand_last']) ?></strong> (<?= e($n['note_type']) ?>): <?= e($n['reason']) ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
