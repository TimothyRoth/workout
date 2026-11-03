<div class="edit-container" id="edit-workout-<?= e($workout['id']) ?>">
    <div class="wrapper">
        <div class="close"><img class="icon edit-icon" src="/img/close.svg" alt="schließen"/></div>
        <div class="inner flex gap-20 column">
            <div class="flex align-center">
                <form class="flex" method="POST" action="/deleteWorkout">
                    <input type="hidden" name="workout_id" value="<?= e($workout['id']) ?>">
                    <input class="deleteButton w-15 h-15 relative bottom-3" type="image" alt="löschen" src="/img/close.svg">
                </form>
                <h3>Workout bearbeiten</h3>
            </div>
            <form class="flex gap-20 align-center" method="POST" action="/editWorkout">
                <input type="hidden" name="workout_id" value="<?= e($workout['id']) ?>">
                <label>
                    <input class="no-border no-padding no-radius" type="text" name="workout_name" placeholder="Name" value="<?= e($workout['name']) ?>" maxlength="100" required>
                </label>
                <input class="saveButton w-20 h-20" type="image" alt="speichern" src="/img/save.svg">
            </form>
        </div>
    </div>
</div>
