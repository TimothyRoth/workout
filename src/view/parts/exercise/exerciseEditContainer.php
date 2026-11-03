<dialog class="sheet" id="add-sets-<?= e($exercise['id']) ?>">
    <div class="sheet-header">
        <h2>Sätze hinzufügen</h2>
        <button class="icon-btn" type="button" data-close aria-label="Schließen"><?= icon('close') ?></button>
    </div>
    <form class="sheet-body" method="POST" action="/addSet">
        <input type="hidden" name="exercise_id" value="<?= e($exercise['id']) ?>">
        <div class="grid-2">
            <label class="field">
                <span>Anzahl Sätze</span>
                <input class="input" type="number" name="amount" min="1" max="10" value="3" inputmode="numeric" required>
            </label>
            <label class="field">
                <span>Wiederholungen</span>
                <input class="input" type="number" name="repetitions" min="0" max="10000" inputmode="numeric" required>
            </label>
            <label class="field">
                <span>Pause (s)</span>
                <input class="input" type="number" name="rest_time" min="0" max="3600" inputmode="numeric" value="90" required>
            </label>
        </div>
        <?php
        $unit = \App\Unit::Kg;
        $load = null;
        include __DIR__ . '/../set/loadFields.php';
        ?>
        <button class="btn btn-primary btn-block" type="submit"><?= icon('plus', 'icon-sm') ?> Hinzufügen</button>
    </form>
    <div class="sheet-footer"></div>
</dialog>

<dialog class="sheet" id="edit-exercise-<?= e($exercise['id']) ?>">
    <div class="sheet-header">
        <h2>Übung bearbeiten</h2>
        <button class="icon-btn" type="button" data-close aria-label="Schließen"><?= icon('close') ?></button>
    </div>
    <form class="sheet-body" method="POST" action="/editExercise">
        <input type="hidden" name="exercise_id" value="<?= e($exercise['id']) ?>">
        <label class="field">
            <span>Name</span>
            <input class="input" type="text" name="exercise_name" value="<?= e($exercise['name']) ?>" maxlength="100" autocomplete="off" required>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Speichern</button>
    </form>
    <div class="sheet-actions">
        <form method="POST" action="/moveExercise">
            <input type="hidden" name="exercise_id" value="<?= e($exercise['id']) ?>">
            <input type="hidden" name="direction" value="up">
            <button class="btn btn-ghost btn-block" type="submit"><?= icon('up', 'icon-sm') ?> Nach oben</button>
        </form>
        <form method="POST" action="/moveExercise">
            <input type="hidden" name="exercise_id" value="<?= e($exercise['id']) ?>">
            <input type="hidden" name="direction" value="down">
            <button class="btn btn-ghost btn-block" type="submit"><?= icon('down', 'icon-sm') ?> Nach unten</button>
        </form>
    </div>
    <form class="sheet-footer" method="POST" action="/deleteExercise" data-confirm="Übung „<?= e($exercise['name']) ?>“ mit allen Sätzen löschen?">
        <input type="hidden" name="exercise_id" value="<?= e($exercise['id']) ?>">
        <button class="btn btn-danger btn-block" type="submit"><?= icon('trash', 'icon-sm') ?> Übung löschen</button>
    </form>
</dialog>
