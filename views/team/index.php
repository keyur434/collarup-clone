<?php $breadcrumbs = nav_trail_dashboard('Team Management'); ?>

<!-- Toolbar -->
<div class="toolbar" style="justify-content:space-between">
    <div style="display:flex;gap:8px;align-items:center">
        <select class="filter-select">
            <option>↕ Sort by</option>
            <option>Name</option>
            <option>Role</option>
            <option>Date Added</option>
        </select>
        <select class="filter-select">
            <option>All</option>
            <option>Owner</option>
            <option>HR Head</option>
            <option>Member</option>
        </select>
    </div>
    <a href="<?= url('team/create') ?>" class="btn btn-primary btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Add Team Member
    </a>
</div>

<!-- Table -->
<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table" data-sortable>
            <thead>
                <tr>
                    <?= th_sort('Name', 'string') ?>
                    <?= th_sort('Role', 'string') ?>
                    <?= th_sort('Email', 'string') ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                <tr><td colspan="4" class="empty-state">No team members yet.</td></tr>
                <?php else: foreach ($members as $m): ?>
                <tr>
                    <td data-label="Name">
                        <a href="<?= url('team/edit/' . $m['id']) ?>" style="color:var(--primary);font-weight:500"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></a>
                    </td>
                    <td data-label="Role"><?= e(ucwords(str_replace('_', ' ', $m['role']))) ?></td>
                    <td data-label="Email"><?= e($m['email']) ?></td>
                    <td data-label="Actions">
                        <div style="position:relative;display:inline-block">
                            <button type="button" class="icon-btn" onclick="toggleTeamMenu(this)" title="Actions">
                                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                            </button>
                            <div class="team-menu hidden">
                                <a href="<?= url('team/edit/' . $m['id']) ?>">Edit</a>
                                <form method="POST" action="<?= url('team/delete/' . $m['id']) ?>" onsubmit="return confirm('Remove this member? They will lose access immediately.')">
                                    <?= csrf_field() ?>
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php $perPageOptions = [15, 25, 50]; include BASE_PATH . '/views/partials/table_pagination.php'; ?>
</div>
<script>
function toggleTeamMenu(btn) {
    var menu = btn.nextElementSibling;
    document.querySelectorAll('.team-menu').forEach(function(m) { if (m !== menu) m.classList.add('hidden'); });
    menu.classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.team-menu') && !e.target.closest('[onclick*="toggleTeamMenu"]')) {
        document.querySelectorAll('.team-menu').forEach(function(m) { m.classList.add('hidden'); });
    }
});
</script>
