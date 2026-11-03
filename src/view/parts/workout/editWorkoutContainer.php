<dialog class="sheet" id="edit-workout-<?= e($workout['id']) ?>">
    <div class="sheet-header">
        <h2>Workout bearbeiten</h2>
        <button class="icon-btn" type="button" data-close aria-label="Schließen"><?= icon('close') ?></button>
    </div>
    <form class="sheet-body" method="POST" action="/editWorkout">
        <input type="hidden" name="workout_id" value="<?= e($workout['id']) ?>">
        <label class="field">
            <span>Name</span>
            <input class="input" type="text" name="workout_name" value="<?= e($workout['name']) ?>" maxlength="100" autocomplete="off" required>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Speichern</button>
    </form>
    <form class="sheet-footer" method="POST" action="/deleteWorkout" data-confirm="Workout „<?= e($workout['name']) ?>“ mit allen Übungen löschen? Das kann nicht rückgängig gemacht werden.">
        <input type="hidden" name="workout_id" value="<?= e($workout['id']) ?>">
        <button class="btn btn-danger btn-block" type="submit"><?= icon('trash', 'icon-sm') ?> Workout löschen</button>
    </form>
</dialog>
