<dialog class="sheet" id="edit-set-<?= e($set['id']) ?>">
    <div class="sheet-header">
        <h2>Satz bearbeiten</h2>
        <button class="icon-btn" type="button" data-close aria-label="Schließen"><?= icon('close') ?></button>
    </div>
    <form class="sheet-body" method="POST" action="/editSet">
        <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
        <div class="grid-2">
            <label class="field">
                <span>Wiederholungen</span>
                <input class="input" type="number" name="repetitions" min="0" max="10000" inputmode="numeric" value="<?= e($set['repetitions']) ?>" required>
            </label>
            <label class="field">
                <span>Pause (s)</span>
                <input class="input" type="number" name="rest_time" min="0" max="3600" inputmode="numeric" value="<?= e($set['rest_time']) ?>" required>
            </label>
        </div>
        <label class="field">
            <span>Einheit (z. B. 80 kg)</span>
            <input class="input" type="text" name="measure_unit" maxlength="30" value="<?= e($set['measure_unit']) ?>" required>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Speichern</button>
    </form>
    <form class="sheet-footer" method="POST" action="/deleteSet" data-confirm="Diesen Satz löschen?">
        <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
        <button class="btn btn-danger btn-block" type="submit"><?= icon('trash', 'icon-sm') ?> Satz löschen</button>
    </form>
</dialog>
