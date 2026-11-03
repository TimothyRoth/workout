<dialog class="sheet" id="edit-set-<?= e($set['id']) ?>">
    <div class="sheet-header">
        <h2>Satz bearbeiten</h2>
        <button class="icon-btn" type="button" data-close aria-label="Schließen"><?= icon('close') ?></button>
    </div>
    <form class="sheet-body" method="POST" action="/editSet">
        <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
        <div class="grid-2">
            <label class="field" for="set-<?= e($set['id']) ?>-reps">
                <span>Wiederholungen</span>
                <?= stepper('<input class="input" id="set-' . e($set['id']) . '-reps" type="number" name="repetitions" min="0" max="10000" inputmode="numeric" data-step="1" value="' . e($set['repetitions']) . '" required>') ?>
            </label>
            <label class="field" for="set-<?= e($set['id']) ?>-rest">
                <span>Pause (s)</span>
                <?= stepper('<input class="input" id="set-' . e($set['id']) . '-rest" type="number" name="rest_time" min="0" max="3600" inputmode="numeric" data-step="15" value="' . e($set['rest_time']) . '" required>') ?>
            </label>
        </div>
        <?php
        $unit = \App\Unit::tryFrom((string)$set['unit']) ?? \App\Unit::Kg;
        $load = $set['load'] === null ? null : (float)$set['load'];
        $fieldId = 'set-' . $set['id'];
        include __DIR__ . '/loadFields.php';
        ?>
        <button class="btn btn-primary btn-block" type="submit">Speichern</button>
    </form>
    <div class="sheet-actions">
        <form method="POST" action="/moveSet">
            <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
            <input type="hidden" name="direction" value="up">
            <button class="btn btn-ghost btn-block" type="submit" aria-label="Nach oben"><?= icon('up', 'icon-sm') ?></button>
        </form>
        <form method="POST" action="/moveSet">
            <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
            <input type="hidden" name="direction" value="down">
            <button class="btn btn-ghost btn-block" type="submit" aria-label="Nach unten"><?= icon('down', 'icon-sm') ?></button>
        </form>
        <form method="POST" action="/duplicateSet" class="grow">
            <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
            <button class="btn btn-ghost btn-block" type="submit"><?= icon('copy', 'icon-sm') ?> Satz kopieren</button>
        </form>
    </div>
    <form class="sheet-footer" method="POST" action="/deleteSet" data-confirm="Diesen Satz löschen?">
        <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
        <button class="btn btn-danger btn-block" type="submit"><?= icon('trash', 'icon-sm') ?> Satz löschen</button>
    </form>
</dialog>
