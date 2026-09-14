<?php $pageBack = ['url' => url('leaderboard'), 'label' => 'Leaderboard'];
function score_ring_html($score) {
    if ($score === null || $score === '') return '<span class="score-ring zero">–</span>';
    $v = (float)$score;
    $cls = $v >= 8 ? 'good' : ($v >= 5 ? 'avg' : 'poor');
    return '<span class="score-ring ' . $cls . '">' . number_format($v, 1) . '</span>';
}
?>

<!-- Toolbar -->
<div class="toolbar" style="justify-content:space-between">
    <form method="GET" style="display:contents">
        <select name="departmentId" class="filter-select" onchange="this.form.submit()" style="min-width:130px">
            <option value="">Informati...</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($departmentId==$d['id'])?'selected':'' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="jobId" class="filter-select" onchange="this.form.submit()" style="min-width:130px">
            <option value="">Sr. Executi...</option>
            <?php foreach ($jobs as $j): ?>
            <option value="<?= $j['id'] ?>" <?= ($jobId===$j['id'])?'selected':'' ?>><?= e($j['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="jobPipelineId" class="filter-select" onchange="this.form.submit()" style="min-width:130px">
            <option value="">Video AI I...</option>
            <?php foreach ($stages as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($stageId===$s['id'])?'selected':'' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="filter-select" style="gap:6px;cursor:default">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            Date
        </div>
        <div class="search-box" style="max-width:220px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" placeholder="Search for candidates" value="<?= e($search ?? '') ?>">
        </div>
    </form>
    <button type="button" class="icon-btn" onclick="toggleLeaderboardSettings()" title="Settings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 0 0 12 2v2a8 8 0 0 1 5.66 2.34M4.93 4.93A10 10 0 0 0 2 12h2a8 8 0 0 1 2.34-5.66M19.07 19.07A10 10 0 0 1 12 22v-2a8 8 0 0 0 5.66-2.34M4.93 19.07A10 10 0 0 0 12 22v-2a8 8 0 0 1-5.66-2.34"/></svg>
    </button>
</div>

<?php if ($jobId && !empty($rows)): ?>
<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>
                        <span class="sort-icon">Candidate Name
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg>
                        </span>
                    </th>
                    <th><span class="sort-icon">Overall <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
                    <th><span class="sort-icon">Experience <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
                    <th><span class="sort-icon">Cultural <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
                    <?php foreach ($criteria as $cr): ?>
                    <th><span class="sort-icon"><?= e($cr['name']) ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td data-label="Candidate">
                        <a href="<?= url('jobs/' . $jobId . '/reports/' . $row['id']) ?>" style="color:var(--primary);font-weight:600"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></a>
                        <div style="font-size:0.78rem;color:var(--text-muted)"><?= format_date($row['completed_at'], 'd M, Y') ?></div>
                    </td>
                    <td data-label="Overall"><?= score_ring_html($row['overall_score']) ?></td>
                    <td data-label="Experience"><?= score_ring_html($row['experience_score']) ?></td>
                    <td data-label="Cultural"><?= score_ring_html($row['culture_score']) ?></td>
                    <?php foreach ($criteria as $cr):
                        $found = null;
                        foreach ($row['criteria_scores'] as $cs) { if ($cs['criterion_name'] === $cr['name']) $found = $cs['score']; }
                    ?>
                    <td data-label="<?= e($cr['name']) ?>"><?= score_ring_html($found) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($jobId): ?>
<div class="empty-state">No completed interviews for this job role yet.</div>
<?php else: ?>
<div class="empty-state">Select a department and job role to view the leaderboard.</div>
<?php endif; ?>

<!-- Settings panel -->
<div class="filter-panel hidden" id="leaderboardSettings">
    <div class="filter-panel-head">
        <h3>Settings</h3>
        <button type="button" class="icon-btn" onclick="toggleLeaderboardSettings()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="filter-panel-body">
        <div class="filter-section">
            <h4>Candidate Filters</h4>
            <h4 style="text-transform:none;font-weight:600;font-size:0.85rem;margin-bottom:8px">Decision</h4>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox"> All</label>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox"> Advance</label>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox"> Hold</label>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox"> Reject</label>
        </div>
        <div class="filter-section">
            <h4>Columns</h4>
            <h4 style="text-transform:none;font-weight:600;font-size:0.85rem;margin-bottom:8px">Scores</h4>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox" checked> Overall Score</label>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox" checked> Experience Score</label>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox" checked> Cultural Score</label>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox"> Soft Skills Score</label>
            <h4 style="text-transform:none;font-weight:600;font-size:0.85rem;margin:10px 0 8px">Candidate</h4>
            <label style="display:flex;gap:8px;font-size:0.85rem;margin-bottom:6px"><input type="checkbox"> Integrity Assessment</label>
        </div>
    </div>
    <div class="filter-foot">
        <button type="button" class="btn btn-outline" onclick="toggleLeaderboardSettings()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="toggleLeaderboardSettings()">Apply</button>
    </div>
</div>
<script>function toggleLeaderboardSettings() { document.getElementById('leaderboardSettings').classList.toggle('hidden'); }</script>
