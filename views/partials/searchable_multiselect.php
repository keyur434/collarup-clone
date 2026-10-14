<?php
/** @var string $smId unique id */
/** @var string $smName form field name ending with [] */
/** @var array $smOptions list of ['value','label'] */
/** @var array $smSelected values */
/** @var string $smPlaceholder */
/** @var string|null $smSearchUrl if set, load options via fetch */
$smId = $smId ?? 'sm_' . uniqid();
$smOptions = $smOptions ?? [];
$smSelected = $smSelected ?? [];
$smPlaceholder = $smPlaceholder ?? 'Search and select…';
$smAllowCustom = !empty($smAllowCustom);
?>
<div class="searchable-ms" id="<?= e($smId) ?>" data-name="<?= e($smName) ?>"<?= !empty($smSearchUrl) ? ' data-search-url="' . e($smSearchUrl) . '"' : '' ?><?= $smAllowCustom ? ' data-allow-custom="1"' : '' ?>>
    <div class="searchable-ms-chips" id="<?= e($smId) ?>_chips"></div>
    <input type="text" class="searchable-ms-input" id="<?= e($smId) ?>_input" placeholder="<?= e($smPlaceholder) ?>" autocomplete="off">
    <div class="searchable-ms-dropdown hidden" id="<?= e($smId) ?>_dropdown"></div>
    <div class="searchable-ms-hidden" id="<?= e($smId) ?>_hidden">
        <?php foreach ($smSelected as $val): ?>
        <input type="hidden" name="<?= e($smName) ?>" value="<?= e($val) ?>">
        <?php endforeach; ?>
    </div>
</div>
<script type="application/json" id="<?= e($smId) ?>_data"><?= json_encode(['options' => $smOptions, 'selected' => $smSelected]) ?></script>
