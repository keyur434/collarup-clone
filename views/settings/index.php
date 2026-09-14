<?php $pageBack = ['url' => url('settings'), 'label' => 'Settings']; ?>

<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p>Manage dropdown master data used across job roles and candidates.</p>
    </div>
</div>

<div class="settings-tabs" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
    <?php foreach ($types as $key => $t): ?>
    <a href="<?= url('settings?type=' . $key) ?>" class="btn btn-sm <?= $type === $key ? 'btn-primary' : 'btn-outline' ?>"><?= e($t['label']) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('settings?type=locations') ?>" class="btn btn-sm <?= $type === 'locations' ? 'btn-primary' : 'btn-outline' ?>">Countries / States / Cities</a>
    <a href="<?= url('settings?type=integrations') ?>" class="btn btn-sm <?= $type === 'integrations' ? 'btn-primary' : 'btn-outline' ?>">Integrations</a>
</div>

<?php if ($type === 'integrations'): ?>
<?php include BASE_PATH . '/views/settings/integrations.php'; ?>
<?php elseif ($type === 'locations'): ?>
<div class="card form-card" style="margin-bottom:16px">
    <h3 style="font-size:0.95rem;margin-bottom:12px">Add Country</h3>
    <form method="POST" action="<?= url('settings/locations') ?>" class="form-grid cols-3">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="country">
        <div class="form-group"><label>Name</label><input name="name" required></div>
        <div class="form-group"><label>Code</label><input name="code" maxlength="5" placeholder="IN"></div>
        <div class="form-group" style="align-self:end"><button class="btn btn-primary btn-sm">Add Country</button></div>
    </form>
</div>
<div class="card form-card" style="margin-bottom:16px">
    <h3 style="font-size:0.95rem;margin-bottom:12px">Add State</h3>
    <form method="POST" action="<?= url('settings/locations') ?>" class="form-grid cols-3">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="state">
        <div class="form-group"><label>Country</label>
            <select name="country_id" required>
                <?php foreach ($countries as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>State name</label><input name="name" required></div>
        <div class="form-group" style="align-self:end"><button class="btn btn-primary btn-sm">Add State</button></div>
    </form>
</div>
<div class="card form-card" style="margin-bottom:16px">
    <h3 style="font-size:0.95rem;margin-bottom:12px">Add City</h3>
    <form method="POST" action="<?= url('settings/locations') ?>" class="form-grid cols-3">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="city">
        <div class="form-group"><label>State</label>
            <select name="state_id" required>
                <?php foreach ($states as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['country_name'] . ' — ' . $s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>City name</label><input name="name" required></div>
        <div class="form-group" style="align-self:end"><button class="btn btn-primary btn-sm">Add City</button></div>
    </form>
</div>
<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Country</th><th>State</th><th>City</th></tr></thead>
            <tbody>
                <?php if (empty($cities)): ?>
                <tr><td colspan="3" class="empty-state">No cities yet. Run <code>php database/seed_locations.php</code> or add manually.</td></tr>
                <?php else: foreach ($cities as $ci): ?>
                <tr>
                    <td><?= e($ci['country_name']) ?></td>
                    <td><?= e($ci['state_name']) ?></td>
                    <td><?= e($ci['name']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="card form-card" style="margin-bottom:16px">
    <h3 style="font-size:0.95rem;margin-bottom:12px">Add <?= e($meta['label']) ?></h3>
    <form method="POST" action="<?= url('settings') ?>" class="form-grid cols-3">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="form-group"><label>Name *</label><input name="name" required></div>
        <?php if ($type === 'countries'): ?>
        <div class="form-group"><label>Code</label><input name="code" maxlength="5"></div>
        <?php endif; ?>
        <?php if ($type === 'seniority' || $type === 'experience'): ?>
        <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="99"></div>
        <?php endif; ?>
        <?php if ($type === 'experience'): ?>
        <div class="form-group"><label>Min years</label><input type="number" step="0.1" name="min_years" value="0"></div>
        <div class="form-group"><label>Max years</label><input type="number" step="0.1" name="max_years"></div>
        <?php endif; ?>
        <div class="form-group" style="align-self:end"><button class="btn btn-primary btn-sm">Add</button></div>
    </form>
</div>
<div class="table-wrap">
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Name</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?><?= !empty($item['code']) ? ' (' . e($item['code']) . ')' : '' ?></td>
                    <td style="text-align:right">
                        <form method="POST" action="<?= url('settings/' . $type . '/' . $item['id'] . '/delete') ?>" onsubmit="return confirm('Delete?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
