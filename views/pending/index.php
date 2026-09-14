<?php
$pageBack = null;
$breadcrumbs = [['label' => 'Pending Recordings']];
?>

<div class="page-header">
    <div>
        <h1>Pending Assignment</h1>
        <p>Recordings ingested from OneDrive awaiting candidate / job assignment (FRD).</p>
    </div>
</div>

<?php if ($msg = flash('success')): ?>
<div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
<div class="alert alert-error"><?= e($msg) ?></div>
<?php endif; ?>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>File</th>
                <th>Recorded</th>
                <th>Size</th>
                <th>Status</th>
                <th>Assign</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recordings)): ?>
            <tr><td colspan="5" class="empty-state">No pending recordings. Run <code>cron/sync_recordings.php</code> after Graph is configured.</td></tr>
            <?php else: foreach ($recordings as $r): ?>
            <tr>
                <td data-label="File"><?= e($r['original_filename'] ?: $r['drive_item_id']) ?></td>
                <td data-label="Recorded"><?= e(format_date($r['recorded_at'] ?: $r['created_at'])) ?></td>
                <td data-label="Size"><?= $r['file_size_bytes'] ? number_format($r['file_size_bytes'] / 1048576, 1) . ' MB' : '—' ?></td>
                <td data-label="Status"><span class="badge badge-gray"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
                <td data-label="Assign">
                    <?php if ($r['status'] === 'pending_assignment'): ?>
                    <form method="POST" action="<?= url('pending-recordings/' . $r['id'] . '/assign') ?>" class="pending-assign-form">
                        <?= csrf_field() ?>
                        <select class="form-control assign-job-filter" style="margin-bottom:6px">
                            <option value="">Filter by job</option>
                            <?php foreach ($jobs as $j): ?>
                            <option value="<?= e($j['id']) ?>"><?= e($j['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="interview_id" class="form-control assign-interview-select" required>
                            <option value="">Select candidate interview…</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">Assign & Analyze</button>
                    </form>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.pending-assign-form').forEach(function(form) {
    var jobSelect = form.querySelector('.assign-job-filter');
    var interviewSelect = form.querySelector('.assign-interview-select');
    function loadInterviews() {
        var jobId = jobSelect.value;
        var url = '<?= url('api/pending/interviews') ?>' + (jobId ? '?job_id=' + encodeURIComponent(jobId) : '');
        fetch(url).then(function(r) { return r.json(); }).then(function(rows) {
            interviewSelect.innerHTML = '<option value="">Select candidate interview…</option>';
            rows.forEach(function(row) {
                var opt = document.createElement('option');
                opt.value = row.interview_id;
                opt.textContent = row.first_name + ' ' + row.last_name + ' — ' + row.job_title;
                interviewSelect.appendChild(opt);
            });
        });
    }
    jobSelect.addEventListener('change', loadInterviews);
    loadInterviews();
});
</script>
