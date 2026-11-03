<?php
/**
 * Unit select + load input pair for set forms.
 * Expects $unit (App\Unit) and $load (?float).
 */
$unit = $unit ?? \App\Unit::Kg;
$load = $load ?? null;
?>
<div class="grid-2">
    <label class="field">
        <span>Einheit</span>
        <select class="input" name="unit" data-unit-select><?= unit_options($unit) ?></select>
    </label>
    <label class="field" data-load-field<?= $unit->needsLoad() ? '' : ' hidden' ?>>
        <span data-load-label><?= e($unit->loadLabel() ?? \App\Unit::Kg->loadLabel()) ?></span>
        <input class="input" type="number" name="load" min="0" step="any" inputmode="decimal"
               value="<?= $load === null ? '' : e(rtrim(rtrim(number_format((float)$load, 2, '.', ''), '0'), '.')) ?>"
               <?= $unit->needsLoad() ? 'required' : 'disabled' ?>>
    </label>
</div>
