<?php $pageBack = ['url' => url('jobs'), 'label' => 'Job Roles']; ?>

<!-- Toolbar -->
<div class="toolbar">
    <form method="GET" style="display:contents">
        <select name="filter" class="filter-select" onchange="this.form.submit()">
            <option value="">Status</option>
            <option value="department" <?= (isset($_GET['filter']) && $_GET['filter']==='department')?'selected':'' ?>>Department</option>
            <option value="date" <?= (isset($_GET['filter']) && $_GET['filter']==='date')?'selected':'' ?>>Date</option>
            <option value="hiring_manager" <?= (isset($_GET['filter']) && $_GET['filter']==='hiring_manager')?'selected':'' ?>>Hiring Manager</option>
        </select>
        <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="active" <?= (($_GET['status'] ?? 'active')==='active')?'selected':'' ?>>Active</option>
            <option value="" <?= (($_GET['status'] ?? 'active')==='') ?'selected':'' ?>>All</option>
            <option value="inactive" <?= (($_GET['status'] ?? '')==='inactive')?'selected':'' ?>>Inactive</option>
            <option value="archived" <?= (($_GET['status'] ?? '')==='archived')?'selected':'' ?>>Archived</option>
        </select>
        <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" placeholder="Search" value="<?= e($search ?? '') ?>">
        </div>
    </form>
    <a href="<?= url('jobs/create') ?>" class="btn btn-primary btn-sm" style="margin-left:auto">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Add Job Role
    </a>
</div>

<!-- Table -->
<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Job Role</th>
                    <th>Role Status</th>
                    <th>Hiring Managers</th>
                    <th>Department</th>
                    <th>Seniority</th>
                    <th>Applicants</th>
                    <th>Candidates</th>
                    <th>Interviews Done</th>
                    <th>Created On</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                <tr><td colspan="9" class="empty-state">No job roles found. <a href="<?= url('jobs/create') ?>">Create one</a>.</td></tr>
                <?php else: foreach ($jobs as $j): ?>
                <tr>
                    <td data-label="Job Role">
                        <a href="<?= url('jobs/' . $j['id'] . '/interviews') ?>" class="link-cell"><?= e($j['title']) ?></a>
                    </td>
                    <td data-label="Status">
                        <span class="badge badge-<?= e($j['status']) ?>"><?= e(ucfirst($j['status'])) ?></span>
                    </td>
                    <td data-label="Hiring Managers">
                        <div class="hm-avatars">
                            <?php foreach (($j['hiring_managers'] ?? []) as $hm): ?>
                            <span class="hm-avatar" title="<?= e($hm['first_name'] . ' ' . $hm['last_name']) ?>"><?= e(strtoupper(substr($hm['first_name'],0,1).substr($hm['last_name']??'',0,1))) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td data-label="Department"><?= e($j['department_name']) ?></td>
                    <td data-label="Seniority"><?= e($j['seniority_name']) ?></td>
                    <td data-label="Applicants"><?= e($j['applicant_count'] ?? 0) ?></td>
                    <td data-label="Candidates"><?= e($j['candidate_count'] ?? 0) ?></td>
                    <td data-label="Interviews Done">
                        <?php $done = $j['interviews_done'] ?? 0; ?>
                        <?php if ($done): ?>
                        <a href="<?= url('jobs/' . $j['id'] . '/interviews') ?>" class="link-cell"><?= e($done) ?></a>
                        <?php else: ?>0<?php endif; ?>
                    </td>
                    <td data-label="Created On"><?= format_date($j['created_at']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <div class="items-per-page">
            Items per page
            <select onchange="window.location=this.value">
                <option value="?perPage=15" <?= (($_GET['perPage'] ?? 15)==15)?'selected':'' ?>>15</option>
                <option value="?perPage=25" <?= (($_GET['perPage'] ?? 15)==25)?'selected':'' ?>>25</option>
                <option value="?perPage=50" <?= (($_GET['perPage'] ?? 15)==50)?'selected':'' ?>>50</option>
                <option value="?perPage=100" <?= (($_GET['perPage'] ?? 15)==100)?'selected':'' ?>>100</option>
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
