<?php $pageBack = ['url' => url('candidates'), 'label' => 'Candidates']; ?>

<!-- Toolbar -->
<div class="toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:8px">
    <form method="GET" style="display:contents" id="candidateFilterForm">
        <?php if (!empty($showArchived)): ?><input type="hidden" name="show_archived" value="1"><?php endif; ?>
        <select name="department_id" class="filter-select" onchange="this.form.submit()">
            <option value="">Departme...</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= (isset($_GET['department_id']) && $_GET['department_id']==$d['id'])?'selected':'' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="job_id" class="filter-select" onchange="this.form.submit()">
            <option value="">Job Role</option>
            <?php foreach ($jobs as $j): ?>
            <option value="<?= $j['id'] ?>" <?= (isset($_GET['job_id']) && $_GET['job_id']==$j['id'])?'selected':'' ?>><?= e($j['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="stage" class="filter-select" onchange="this.form.submit()">
            <option value="">Interview...</option>
            <option value="round1" <?= (($_GET['stage']??'')==='round1')?'selected':'' ?>>Round 1</option>
            <option value="round2" <?= (($_GET['stage']??'')==='round2')?'selected':'' ?>>Round 2</option>
            <option value="final" <?= (($_GET['stage']??'')==='final')?'selected':'' ?>>Final Round</option>
        </select>
        <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" placeholder="Search Candidates" value="<?= e($search ?? '') ?>">
        </div>
        <button type="button" class="icon-btn" title="Refresh" onclick="this.form.submit()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        </button>
    </form>
    <div style="display:flex;gap:8px;align-items:center">
        <label style="font-size:0.82rem;display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" value="1" <?= !empty($showArchived) ? 'checked' : '' ?>
                onchange="window.location='<?= url('candidates') ?>?show_archived=' + (this.checked ? '1' : '0')">
            Show archived
        </label>
        <a href="<?= url('candidates/create') ?>" class="btn btn-primary btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Add Candidate
        </a>
        <button type="button" class="icon-btn" onclick="toggleFilterPanel()" title="Filters">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
        </button>
    </div>
</div>

<!-- Bulk bar -->
<?php if (!empty($canBulkManage)): ?>
<form method="POST" action="<?= url('candidates/bulk') ?>" id="bulkForm">
    <?= csrf_field() ?>
    <?php if (!empty($showArchived)): ?><input type="hidden" name="show_archived" value="1"><?php endif; ?>
    <div class="toolbar" style="margin-bottom:10px;padding:8px 12px;background:var(--gray-50);border-radius:8px;display:none" id="bulkBar">
        <span id="bulkCount" style="font-size:0.82rem;margin-right:12px">0 selected</span>
        <button type="submit" name="action" value="archive" class="btn btn-outline btn-sm" onclick="return confirm('Archive selected?')">Archive</button>
        <button type="submit" name="action" value="reactivate" class="btn btn-outline btn-sm">Reactivate</button>
        <button type="submit" name="action" value="delete" class="btn btn-reject btn-sm" onclick="return confirm('Delete selected permanently?')">Delete</button>
        <button type="button" class="btn btn-outline btn-sm" id="compareBtn">Compare</button>
    </div>
<?php endif; ?>

<!-- Table -->
<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <?php if (!empty($canBulkManage)): ?><th style="width:36px"><input type="checkbox" id="selectAllCandidates"></th><?php endif; ?>
                    <th>Candidate Name</th>
                    <th>Job Role</th>
                    <th>Department</th>
                    <th>Stage</th>
                    <th>Status</th>
                    <th>Date &amp; Time</th>
                    <th>Candidate score</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($candidates)): ?>
                <tr><td colspan="<?= !empty($canBulkManage) ? 9 : 8 ?>" class="empty-state">No candidates found.</td></tr>
                <?php else: foreach ($candidates as $c):
                    $sl = $c['overall_score'] ? score_label($c['overall_score']) : null;
                ?>
                <tr>
                    <?php if (!empty($canBulkManage)): ?>
                    <td><input type="checkbox" name="application_ids[]" value="<?= e($c['application_id']) ?>" class="bulk-check" data-job-id="<?= e($c['job_id']) ?>"></td>
                    <?php endif; ?>
                    <td data-label="Candidate">
                        <div class="candidate-cell">
                            <span class="av"><?= e(strtoupper(substr($c['first_name'],0,1).substr($c['last_name']??'',0,1))) ?></span>
                            <div>
                                <div style="font-weight:600;color:var(--gray-900)"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                <?php if (($c['email_app_count'] ?? 1) > 1): ?>
                                <span class="badge badge-amber" style="font-size:0.65rem;margin-top:2px">Multi-job</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td data-label="Job Role"><span style="color:var(--primary);font-weight:500"><?= e($c['job_title']) ?></span></td>
                    <td data-label="Department"><?= e($c['department_name']) ?></td>
                    <td data-label="Stage"><?= e($c['stage_name']) ?></td>
                    <td data-label="Status"><span class="badge badge-<?= e($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                    <td data-label="Date &amp; Time" style="white-space:nowrap;font-size:0.82rem">
                        <?= $c['scheduled_date'] ? format_date($c['scheduled_date']) : '–' ?>
                        <?= ($c['scheduled_start'] && $c['scheduled_end']) ? ' · ' . format_time($c['scheduled_start']) . '–' . format_time($c['scheduled_end']) : '' ?>
                    </td>
                    <td data-label="Score">
                        <?php if ($sl): ?>
                        <span class="score-badge <?= e($sl['class'] === 'score-good' ? 'good' : ($sl['class'] === 'score-average' ? 'avg' : 'poor')) ?>">
                            <?= e($c['overall_score']) ?> <?= e($sl['label']) ?>
                        </span>
                        <?php else: ?>–<?php endif; ?>
                    </td>
                    <td data-label="Action">
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">
                        <a href="<?= url('applications/' . $c['application_id'] . '/manage') ?>" style="color:var(--primary);font-weight:600;font-size:0.82rem">Manage</a>
                        <?php if (!empty($c['resume_path'])): ?>
                        <a href="<?= resume_url($c['application_id']) ?>" style="font-size:0.78rem;color:var(--gray-600)">Resume</a>
                        <?php endif; ?>
                        <?php if (!empty($c['is_archived'])): ?>
                        <?php if (!empty($canBulkManage)): ?>
                        <button type="submit" form="reactivate-<?= e($c['application_id']) ?>" class="btn btn-outline btn-sm" style="font-size:0.72rem;padding:2px 8px">Reactivate</button>
                        <?php else: ?>
                        <span class="badge badge-gray" style="font-size:0.7rem">Archived</span>
                        <?php endif; ?>
                        <?php elseif ($c['interview_id'] && empty($c['report_id'])): ?>
                        <a href="<?= url('jobs/' . $c['job_id'] . '/interviews') ?>" style="font-size:0.78rem">Upload Interview</a>
                        <?php elseif ($c['report_id']): ?>
                        <a href="<?= url('jobs/' . $c['job_id'] . '/reports/' . $c['report_id']) ?>" style="font-size:0.78rem">View Report</a>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <div class="items-per-page">
            Items per page
            <select onchange="window.location=this.value">
                <option value="?perPage=15">15</option>
                <option value="?perPage=25">25</option>
                <option value="?perPage=50">50</option>
            </select>
        </div>
        <?php if (isset($pagination)): ?>
        <div class="pagination">
            <?php if ($pagination['prev']): ?><a href="?page=<?= $pagination['prev'] ?>">&#8249;</a><?php else: ?><span style="opacity:.4">&#8249;</span><?php endif; ?>
            <?php foreach ($pagination['pages'] as $p): ?>
                <?php if ($p === '...'): ?><span class="ellipsis">…</span>
                <?php elseif ($p == $pagination['current']): ?><a class="active"><?= $p ?></a>
                <?php else: ?><a href="?page=<?= $p ?>"><?= $p ?></a><?php endif; ?>
            <?php endforeach; ?>
            <?php if ($pagination['next']): ?><a href="?page=<?= $pagination['next'] ?>">&#8250;</a><?php else: ?><span style="opacity:.4">&#8250;</span><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php if (!empty($canBulkManage)): ?>
</form>
<?php foreach ($candidates ?? [] as $c): if (empty($c['is_archived'])) continue; ?>
<form method="POST" action="<?= url('applications/' . $c['application_id'] . '/reactivate') ?>" id="reactivate-<?= e($c['application_id']) ?>" style="display:none"><?= csrf_field() ?></form>
<?php endforeach; ?>
<?php endif; ?>

<!-- Filter Panel (right drawer) -->
<div class="filter-panel hidden" id="filterPanel">
    <div class="filter-panel-head">
        <h3>Filters</h3>
        <button type="button" class="icon-btn" onclick="toggleFilterPanel()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <form method="GET" id="filterPanelForm">
        <div class="filter-panel-body">
            <div class="filter-section">
                <h4>Decision</h4>
                <select name="decision" class="form-control">
                    <option value="">Select</option>
                    <option value="offer">Offer</option>
                    <option value="rejected">Rejected</option>
                    <option value="hold">Hold</option>
                </select>
            </div>
            <div class="filter-section">
                <h4>Years of Experience</h4>
                <select name="experience" class="form-control">
                    <option value="">Select</option>
                    <option value="0-1">Less Than 1 Year</option>
                    <option value="1-2">1 To 2 Years</option>
                    <option value="3-5">3 To 5 Years</option>
                    <option value="6-10">6 To 10 Years</option>
                    <option value="10+">10+ Years</option>
                </select>
            </div>
            <div class="filter-section">
                <h4>Status</h4>
                <select name="status" class="form-control">
                    <option value="">Select</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="processing">Processing</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="incomplete">Incomplete</option>
                </select>
            </div>
            <div class="filter-section">
                <h4>Source</h4>
                <select name="source_id" class="form-control">
                    <option value="">Select</option>
                    <?php foreach ($sources ?? [] as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-foot">
            <button type="button" class="btn btn-outline" onclick="toggleFilterPanel()">Discard</button>
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
    </form>
</div>
<script>
function toggleFilterPanel() {
    var p = document.getElementById('filterPanel');
    p.classList.toggle('hidden');
}
(function() {
    var all = document.getElementById('selectAllCandidates');
    var bar = document.getElementById('bulkBar');
    var count = document.getElementById('bulkCount');
    if (!all || !bar) return;
    function updateBulk() {
        var checks = document.querySelectorAll('.bulk-check:checked');
        bar.style.display = checks.length ? 'flex' : 'none';
        if (count) count.textContent = checks.length + ' selected';
    }
    all.addEventListener('change', function() {
        document.querySelectorAll('.bulk-check').forEach(function(c) { c.checked = all.checked; });
        updateBulk();
    });
    document.querySelectorAll('.bulk-check').forEach(function(c) {
        c.addEventListener('change', updateBulk);
    });
    var compareBtn = document.getElementById('compareBtn');
    if (compareBtn) {
        compareBtn.addEventListener('click', function() {
            var checks = document.querySelectorAll('.bulk-check:checked');
            if (checks.length < 2) { alert('Select at least 2 candidates to compare.'); return; }
            if (checks.length > 4) { alert('Compare up to 4 candidates at a time.'); return; }
            var jobId = checks[0].getAttribute('data-job-id');
            for (var i = 0; i < checks.length; i++) {
                if (checks[i].getAttribute('data-job-id') !== jobId) {
                    alert('Compare candidates from the same job only.');
                    return;
                }
            }
            var ids = [];
            checks.forEach(function(c) { ids.push(c.value); });
            window.location = '<?= url('jobs') ?>/' + jobId + '/compare?ids=' + ids.join(',');
        });
    }
})();
</script>
