<div class="page-header">
    <div>
        <h1><?= $member ? 'Edit Team Member' : 'Add Team Member' ?></h1>
        <p>Manage access and roles for your hiring team.</p>
    </div>
</div><form method="POST" action="<?= $member ? url('team/edit/' . $member['id']) : url('team/create') ?>" class="card form-card">
    <?= csrf_field() ?>
    <div class="form-grid cols-2">
        <div class="form-group"><label>First Name *</label><input name="first_name" value="<?= e($member['first_name'] ?? '') ?>" required></div>
        <div class="form-group"><label>Last Name *</label><input name="last_name" value="<?= e($member['last_name'] ?? '') ?>" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= e($member['email'] ?? '') ?>" required></div>
        <div class="form-group"><label>Role *</label>
            <select name="role" required>
                <option value="owner" <?= ($member['role'] ?? '') === 'owner' ? 'selected' : '' ?>>Owner</option>
                <option value="hr_head" <?= ($member['role'] ?? '') === 'hr_head' ? 'selected' : '' ?>>HR Head</option>
                <option value="member" <?= ($member['role'] ?? 'member') === 'member' ? 'selected' : '' ?>>Member</option>
            </select>
        </div>
        <div class="form-group">
            <label>Password <?= $member ? '(leave blank to keep)' : '(optional)' ?></label>
            <input type="password" name="password" autocomplete="new-password">
            <?php if (!empty($ssoEnabled)): ?>
            <p class="hint" style="margin-top:6px">For Microsoft SSO, leave password empty. Email must match their Microsoft work account exactly (same as Outlook/Teams).</p>
            <?php if (!config('app')['allow_local_login']): ?>
            <p class="hint" style="margin-top:4px">Local password login is disabled — they must use <strong>Sign in with Microsoft</strong>.</p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="form-actions">
        <a href="<?= url('team') ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><?= $member ? 'Update' : 'Add' ?></button>
    </div>
</form>
