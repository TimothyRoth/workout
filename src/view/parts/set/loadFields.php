<?php
/**
 * Unit select + load input pair for set forms.
 * Expects $unit (App\Unit), $load (?float) and $fieldId (prefix for the input ids,
 * so the labels can point at the inputs rather than the stepper buttons).
 */
$unit = $unit ?? \App\Unit::Kg;
$load = $load ?? null;
$fieldId = $fieldId ?? 'load-' . uniqid();
?>
<div class="grid-2">
    <label class="field">
        <span>Einheit</span>
        <select class="input" name="unit" data-unit-select><?= unit_options($unit) ?></select>
    </label>
    <label class="field" for="<?= e($fieldId) ?>-load" data-load-field<?= $unit->needsLoad() ? '' : ' hidden' ?>>
        <span data-load-label><?= e($unit->loadLabel() ?? \App\Unit::Kg->loadLabel()) ?></span>
        <?= stepper(sprintf(
            '<input class="input" id="%s-load" type="number" name="load" min="0" step="any" inputmode="decimal" data-step="%s" value="%s" %s>',
            e($fieldId),
            e((string)$unit->loadStep()),
            $load === null ? '' : e(rtrim(rtrim(number_format((float)$load, 2, '.', ''), '0'), '.')),
            $unit->needsLoad() ? 'required' : 'disabled'
        )) ?>
    </label>
</div>
