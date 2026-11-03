<div class="edit-container" id="edit-sets-<?= e($exercise['id']) ?>">
    <div class="wrapper">
        <div class="close">
            <img class="icon edit-icon" src="/img/close.svg" alt="schließen"/>
        </div>
        <h3>Satz hinzufügen</h3>
        <form class="addSet" method="POST" action="/addSet">
            <input type="hidden" name="exercise_id" value="<?= e($exercise['id']) ?>">
            <div class="flex column gap-20">
                <label class="flex column gap-10">
                    Satzanzahl
                    <input type="number" name="amount" min="1" max="10" value="3" inputmode="numeric" required/>
                </label>
                <label class="flex column gap-10">
                    Wiederholungen
                    <input type="number" name="repetitions" min="0" max="10000" inputmode="numeric" required/>
                </label>
                <label class="flex column gap-10">
                    Einheit
                    <input type="text" name="measure_unit" maxlength="30" required/>
                </label>
                <label class="flex column gap-10">
                    Pausenzeit (s)
                    <input type="number" name="rest_time" min="0" max="3600" inputmode="numeric" required/>
                </label>
                <div class="flex justify-center">
                    <input class="addButton" type="image" alt="hinzufügen" src="/img/add.svg">
                </div>
            </div>
        </form>
    </div>
</div>

<div class="edit-container" id="edit-exercise-<?= e($exercise['id']) ?>">
    <div class="wrapper">
        <div class="close">
            <img class="icon edit-icon" src="/img/close.svg" alt="schließen"/>
        </div>
        <div class="inner flex column gap-20">
            <div class="flex align-center">
                <form class="flex align-center" method="POST" action="/deleteExercise">
                    <input type="hidden" name="exercise_id" value="<?= e($exercise['id']) ?>">
                    <input class="deleteButton w-15 h-15 relative bottom-3" type="image" alt="löschen" src="/img/close.svg">
                </form>
                <h3 class="m-0">Übung bearbeiten</h3>
            </div>
            <form class="flex gap-20 align-center" method="POST" action="/editExercise">
                <input type="hidden" name="exercise_id" value="<?= e($exercise['id']) ?>">
                <label>
                    <input class="no-border no-padding no-radius" type="text" name="exercise_name" value="<?= e($exercise['name']) ?>" maxlength="100" required>
                </label>
                <input class="saveButton w-20 h-20" type="image" alt="speichern" src="/img/save.svg">
            </form>
        </div>
    </div>
</div>
