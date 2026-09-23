<?php $pageBack = ['url' => url('pipeline'), 'label' => 'Pipeline']; ?>

<!-- Job selector -->
<div class="toolbar" style="margin-bottom:14px">
    <form method="GET" style="display:contents">
        <select name="job_id" class="filter-select" onchange="this.form.submit()" style="min-width:200px">
            <option value="">Select Job Role</option>
            <?php foreach ($jobs as $j): ?>
            <option value="<?= $j['id'] ?>" <?= ($jobId === $j['id'])?'selected':'' ?>><?= e($j['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($jobId): ?>
<!-- Kanban -->
<div class="kanban-wrap">
    <div class="kanban">
        <?php foreach ($columns as $col): ?>
        <div class="kanban-col">
            <div class="kanban-col-header"><?= e($col['name']) ?></div>
            <?php if (empty($col['candidates'])): ?>
            <div style="font-size:0.78rem;color:var(--text-muted);text-align:center;padding:12px 0">Empty</div>
            <?php else: foreach ($col['candidates'] as $c): ?>
            <div class="kanban-card">
                <div class="kc-name">
                    <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                    <?php if ($c['status']): ?>
                    <span class="badge badge-<?= e($c['status']) ?>" style="font-size:0.7rem"><?= e(ucfirst($c['status'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="kc-email"><?= e($c['email']) ?></div>
                <div class="kc-meta">
                    <span><?= e($c['job_title']) ?></span>
                    <span><?= $c['scheduled_date'] ? format_date($c['scheduled_date'], 'd M, Y') : '' ?></span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="empty-state">Select a job role to view the pipeline.</div>
<?php endif; ?>
