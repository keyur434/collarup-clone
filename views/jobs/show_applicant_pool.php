<?php
$breadcrumbs = [
    ['label' => $job['title'], 'url' => url('jobs/' . $job['id'] . '/interviews')],
    ['label' => 'Applicant Pool'],
];
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <h1 style="font-size:1rem;font-weight:700"><?= e($job['title']) ?></h1>
            <span class="badge badge-<?= e($job['status']) ?>"><?= e(ucfirst($job['status'])) ?></span>
        </div>
        <p style="font-size:0.82rem;color:var(--text-muted)"><?= e($job['city_name'] ?? '') ?><?= ($job['city_name'] && $job['state_name']) ? ', ' : '' ?><?= e($job['state_name'] ?? '') ?> · <?= e($job['seniority_name']) ?></p>
    </div>
    <a href="<?= url('candidates/create?job_id=' . $job['id']) ?>" class="btn btn-primary btn-sm">+ Add Candidate</a>
</div>

<div style="display:flex;border-bottom:1px solid var(--border);margin-bottom:16px">
    <a href="<?= url('jobs/' . $job['id'] . '/interviews?tab=applicant-pool') ?>"
       style="padding:8px 16px;font-size:0.875rem;font-weight:600;color:var(--primary);border-bottom:2px solid var(--primary);margin-bottom:-1px">Applicant Pool</a>
    <a href="<?= url('jobs/' . $job['id'] . '/interviews') ?>"
       style="padding:8px 16px;font-size:0.875rem;font-weight:500;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-1px">Interviews</a>
</div>

<div class="toolbar" style="margin-bottom:14px">
    <form method="GET" style="display:contents">
        <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" placeholder="Search for candidates" value="<?= e($search ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-outline btn-sm">Search</button>
    </form>
</div>

<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th><input type="checkbox" style="width:14px"></th>
                    <th>Candidate Name</th>
                    <th>Application Date</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Experience</th>
                    <th>Match Score</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applicants)): ?>
                <tr><td colspan="7" class="empty-state">No applicants yet.</td></tr>
                <?php else: foreach ($applicants as $a): ?>
                <tr>
                    <td><input type="checkbox"></td>
                    <td data-label="Candidate">
                        <div class="candidate-cell">
                            <span class="av"><?= e(strtoupper(substr($a['first_name'],0,1).substr($a['last_name']??'',0,1))) ?></span>
                            <span style="font-weight:500"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></span>
                        </div>
                    </td>
                    <td data-label="Applied"><?= format_date($a['application_date']) ?></td>
                    <td data-label="Company"><?= e($a['current_company'] ?: '–') ?></td>
                    <td data-label="Location"><?= e($a['current_location'] ?: '–') ?></td>
                    <td data-label="Experience"><?= e($a['years_experience'] ?: '–') ?></td>
                    <td data-label="Match"><?= e($a['match_score_grade'] ?: '–') ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <div class="items-per-page">Items per page <select><option>15</option><option>25</option></select></div>
    </div>
</div>
