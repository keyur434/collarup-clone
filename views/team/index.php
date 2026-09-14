<?php $pageBack = ['url' => url('team'), 'label' => 'Team Management']; ?>

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
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                <tr><td colspan="4" class="empty-state">No team members yet.</td></tr>
                <?php else: foreach ($members as $m): ?>
                <tr>
                    <td data-label="Name">
                        <?php if ($m['role'] !== 'member'): ?>
                        <a href="<?= url('team/edit/' . $m['id']) ?>" style="color:var(--primary);font-weight:500"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></a>
                        <?php else: ?>
                        <span style="color:var(--gray-600)"><?= e($m['email']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Role"><?= e(ucwords(str_replace('_', ' ', $m['role']))) ?></td>
                    <td data-label="Email"><?= e($m['email']) ?></td>
                    <td data-label="Actions">
                        <div style="position:relative;display:inline-block">
                            <button type="button" class="icon-btn" onclick="toggleTeamMenu(this)" title="Actions">
                                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                            </button>
                            <div class="team-menu hidden" style="position:absolute;right:0;top:100%;background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);min-width:130px;z-index:10;padding:4px 0">
                                <a href="<?= url('team/edit/' . $m['id']) ?>" style="display:block;padding:8px 14px;font-size:0.85rem;color:var(--gray-700)" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''">Edit</a>
                                <form method="POST" action="<?= url('team/delete/' . $m['id']) ?>" onsubmit="return confirm('Delete this member?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" style="display:block;width:100%;text-align:left;padding:8px 14px;font-size:0.85rem;color:var(--red);border:none;background:none;cursor:pointer" onmouseover="this.style.background='var(--red-bg)'" onmouseout="this.style.background=''">Delete</button>
                                </form>
                            </div>
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
            <select><option>15</option><option>25</option><option>50</option></select>
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
