<?php
$perPageOptions = $perPageOptions ?? [15, 25, 50, 100];
$currentPerPage = (int) ($perPage ?? ($_GET['perPage'] ?? $perPageOptions[0]));
?>
<div class="table-footer">
    <div class="items-per-page">
        Items per page
        <select onchange="window.location=this.value">
            <?php foreach ($perPageOptions as $opt): ?>
            <option value="<?= e(page_query(['perPage' => (int) $opt, 'page' => 1])) ?>" <?= $currentPerPage === (int) $opt ? 'selected' : '' ?>><?= (int) $opt ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (isset($pagination)): ?>
    <div class="pagination">
        <?php if ($pagination['prev']): ?><a href="<?= e(page_query(['page' => $pagination['prev']])) ?>">&#8249;</a><?php else: ?><span style="opacity:.4">&#8249;</span><?php endif; ?>
        <?php foreach ($pagination['pages'] as $p): ?>
            <?php if ($p === '...'): ?><span class="ellipsis">…</span>
            <?php elseif ($p == $pagination['current']): ?><a class="active"><?= $p ?></a>
            <?php else: ?><a href="<?= e(page_query(['page' => $p])) ?>"><?= $p ?></a><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($pagination['next']): ?><a href="<?= e(page_query(['page' => $pagination['next']])) ?>">&#8250;</a><?php else: ?><span style="opacity:.4">&#8250;</span><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
