<?php $breadcrumbs = nav_trail_dashboard('Job Roles'); ?>

<!-- Toolbar -->
<div class="toolbar">
    <form method="GET" style="display:contents">
        <select name="scope" class="filter-select" onchange="this.form.submit()" title="Ownership">
            <option value="" <?= empty($scope) ? 'selected' : '' ?>>All job roles</option>
            <option value="mine" <?= ($scope ?? '') === 'mine' ? 'selected' : '' ?>>Created by me</option>
        </select>
        <select name="order" class="filter-select" onchange="this.form.submit()">
            <option value="created_desc" <?= ($order ?? 'created_desc') === 'created_desc' ? 'selected' : '' ?>>Newest first</option>
            <option value="title" <?= ($order ?? '') === 'title' ? 'selected' : '' ?>>Job title A–Z</option>
            <option value="location" <?= ($order ?? '') === 'location' ? 'selected' : '' ?>>Location A–Z</option>
            <option value="creator" <?= ($order ?? '') === 'creator' ? 'selected' : '' ?>>Creator name</option>
        </select>
        <?php if (!empty($locationStates)): ?>
        <select name="state_id" class="filter-select" onchange="this.form.submit()" title="State">
            <option value="">All states</option>
            <?php foreach ($locationStates as $st): ?>
            <option value="<?= (int) $st['id'] ?>" <?= (int) ($stateFilter ?? 0) === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if (!empty($locationCities)): ?>
        <select name="city_id" class="filter-select" onchange="this.form.submit()" title="City">
            <option value="">All cities</option>
            <?php foreach ($locationCities as $ci): ?>
            <option value="<?= (int) $ci['id'] ?>" <?= (int) ($cityFilter ?? 0) === (int) $ci['id'] ? 'selected' : '' ?>><?= e($ci['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if (!empty($teamUsers)): ?>
        <select name="created_by" class="filter-select" onchange="this.form.submit()">
            <option value="">All creators</option>
            <?php foreach ($teamUsers as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= (isset($_GET['created_by']) && (int) $_GET['created_by'] === (int) $u['id']) ? 'selected' : '' ?>><?= e(user_display_name($u['first_name'], $u['last_name'])) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="active" <?= (($_GET['status'] ?? 'active')==='active')?'selected':'' ?>>Active</option>
            <option value="" <?= (($_GET['status'] ?? 'active')==='') ?'selected':'' ?>>All</option>
            <option value="inactive" <?= (($_GET['status'] ?? '')==='inactive')?'selected':'' ?>>Inactive</option>
            <option value="archived" <?= (($_GET['status'] ?? '')==='archived')?'selected':'' ?>>Archived</option>
        </select>
        <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" placeholder="Search title, city, state" value="<?= e($search ?? '') ?>">
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
        <table class="data-table jobs-table" data-sortable>
            <thead>
                <tr>
                    <?= th_sort('Job Role', 'string', null, 'jr-sticky jr-sticky-title') ?>
                    <?= th_sort('Location', 'string', null, 'jr-col-fixed') ?>
                    <?= th_sort('Role Status', 'string', null, 'jr-col-fixed') ?>
                    <th class="jr-col-fixed">Hiring Managers</th>
                    <?= th_sort('Created by', 'string', null, 'jr-col-fixed') ?>
                    <?= th_sort('Department', 'string', null, 'jr-col-fixed') ?>
                    <?= th_sort('Seniority', 'string', null, 'jr-col-fixed') ?>
                    <?= th_sort('Applicants', 'number', null, 'jr-col-fixed') ?>
                    <?= th_sort('Candidates', 'number', null, 'jr-col-fixed') ?>
                    <?= th_sort('Interviews Done', 'number', null, 'jr-col-fixed') ?>
                    <?= th_sort('Created On', 'date', null, 'jr-col-fixed') ?>
                    <th class="jr-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                <tr><td colspan="12" class="empty-state">No job roles found. <a href="<?= url('jobs/create') ?>">Create one</a>.</td></tr>
                <?php else: foreach ($jobs as $j): ?>
                <tr>
                    <td class="jr-sticky jr-sticky-title" data-label="Job Role" data-sort-value="<?= e(strtolower($j['title'])) ?>">
                        <a href="<?= url('jobs/' . $j['id'] . '/interviews') ?>" class="link-cell"><?= e($j['title']) ?></a>
                    </td>
                    <td class="jr-col-fixed" data-label="Location" data-sort-value="<?= e(strtolower(job_location_label($j['city_name'] ?? '', $j['state_name'] ?? ''))) ?>">
                        <?= e(job_location_label($j['city_name'] ?? '', $j['state_name'] ?? '')) ?>
                    </td>
                    <td class="jr-col-fixed" data-label="Status" data-sort-value="<?= e(strtolower($j['status'])) ?>">
                        <span class="badge badge-<?= e($j['status']) ?>"><?= e(ucfirst($j['status'])) ?></span>
                    </td>
                    <td class="jr-col-fixed" data-label="Hiring Managers">
                        <?php $hms = $j['hiring_managers'] ?? []; ?>
                        <?php if (empty($hms)): ?>
                        <span class="text-muted" style="font-size:0.82rem">—</span>
                        <?php else: ?>
                        <div class="hm-avatars" title="<?= e(implode(', ', array_map(function ($hm) {
                            return trim($hm['first_name'] . ' ' . ($hm['last_name'] ?? ''));
                        }, $hms))) ?>">
                            <?php foreach ($hms as $hm):
                                $hmName = trim($hm['first_name'] . ' ' . ($hm['last_name'] ?? ''));
                                $hmIni = !empty($hm['initials'])
                                    ? $hm['initials']
                                    : strtoupper(substr($hm['first_name'], 0, 1) . substr($hm['last_name'] ?? '', 0, 1));
                                if ($hmIni === '') {
                                    $hmIni = '?';
                                }
                            ?>
                            <span class="hm-avatar" title="<?= e($hmName) ?>"><?= e($hmIni) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="jr-col-fixed" data-label="Created by" data-sort-value="<?= e(strtolower(user_display_name($j['creator_first_name'] ?? '', $j['creator_last_name'] ?? ''))) ?>"><?= e(user_display_name($j['creator_first_name'] ?? '', $j['creator_last_name'] ?? '')) ?></td>
                    <td class="jr-col-fixed" data-label="Department" data-sort-value="<?= e(strtolower($j['department_name'] ?? '')) ?>"><?= e($j['department_name']) ?></td>
                    <td class="jr-col-fixed" data-label="Seniority" data-sort-value="<?= e(strtolower($j['seniority_name'] ?? '')) ?>"><?= e($j['seniority_name']) ?></td>
                    <td class="jr-col-fixed" data-label="Applicants" data-sort-value="<?= (int) ($j['applicants'] ?? 0) ?>"><?= e($j['applicants'] ?? 0) ?></td>
                    <td class="jr-col-fixed" data-label="Candidates" data-sort-value="<?= (int) ($j['candidates'] ?? 0) ?>"><?= e($j['candidates'] ?? 0) ?></td>
                    <td class="jr-col-fixed" data-label="Interviews Done" data-sort-value="<?= (int) ($j['interviews_done'] ?? 0) ?>">
                        <?php $done = $j['interviews_done'] ?? 0; ?>
                        <?php if ($done): ?>
                        <a href="<?= url('jobs/' . $j['id'] . '/interviews') ?>" class="link-cell"><?= e($done) ?></a>
                        <?php else: ?>0<?php endif; ?>
                    </td>
                    <td class="jr-col-fixed" data-label="Created On" data-sort-value="<?= e($j['created_at']) ?>"><?= format_date($j['created_at']) ?></td>
                    <td class="jr-col-actions" data-label="Actions">
                        <a href="<?= url('jobs/' . $j['id'] . '/interviews') ?>" class="btn btn-outline btn-sm">Interviews</a>
                        <?php if (!empty($j['can_manage'])): ?>
                        <a href="<?= url('jobs/edit/' . $j['id']) ?>" class="btn btn-outline btn-sm">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php include BASE_PATH . '/views/partials/table_pagination.php'; ?>
</div>
