<div class="card form-card" style="margin-bottom:16px">
    <h3 style="font-size:0.95rem;margin-bottom:12px">Email &amp; notifications</h3>
    <p class="hint" style="margin-bottom:12px">Configure SMTP in <code>.env</code> (<code>SMTP_HOST</code>, <code>SMTP_USERNAME</code>, etc.). Emails log to notification_log when sent.</p>
    <form method="POST" action="<?= url('settings/integrations') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label><input type="checkbox" name="notify_report_ready" value="1" <?= !empty($settings['notify_report_ready']) ? 'checked' : '' ?>> Email hiring managers when AI report is ready</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="notify_decision_email" value="1" <?= !empty($settings['notify_decision_email']) ? 'checked' : '' ?>> Email candidate on advance / reject decision</label>
        </div>
        <h3 style="font-size:0.9rem;margin:20px 0 10px">Data retention</h3>
        <div class="form-group">
            <label><input type="checkbox" name="retention_auto_archive" value="1" <?= !empty($settings['retention_auto_archive']) ? 'checked' : '' ?>> Auto-archive completed applications after N days (cron: <code>retention.php</code>)</label>
        </div>
        <div class="form-group">
            <label>Archive after (days)</label>
            <input type="number" name="retention_days_completed" min="30" value="<?= (int) $settings['retention_days_completed'] ?>" style="max-width:120px">
        </div>
        <h3 style="font-size:0.9rem;margin:20px 0 10px">Keka HR</h3>
        <p class="hint">Set <code>KEKA_*</code> in .env. Cached employees: <?= (int) $keka_count ?><?php if ($settings['keka_last_sync_at']): ?> · Last sync: <?= e($settings['keka_last_sync_at']) ?><?php endif; ?></p>
        <div style="display:flex;gap:8px;margin-top:12px">
            <button type="submit" class="btn btn-primary btn-sm">Save settings</button>
            <button type="submit" name="run_keka_sync" value="1" class="btn btn-outline btn-sm">Run Keka sync now</button>
        </div>
    </form>
</div>

<?php if (!empty($notification_log)): ?>
<div class="card form-card">
    <h3 style="font-size:0.95rem;margin-bottom:12px">Recent notification log</h3>
    <table class="data-table">
        <thead><tr><th>Type</th><th>To</th><th>Subject</th><th>Status</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($notification_log as $n): ?>
        <tr>
            <td><?= e($n['notification_type']) ?></td>
            <td><?= e($n['recipient']) ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= e($n['subject']) ?></td>
            <td><?= e($n['status']) ?></td>
            <td><?= e(format_date($n['created_at'], 'd M H:i')) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
