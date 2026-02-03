<div class="edit-container" id="edit-sets-<?= $exercise['id'] ?>">
    <div class="wrapper">
        <div class="close">
            <img class="icon edit-icon" src="/img/close.svg" alt="edit-icon"/>
        </div>
        <h3>Satz hinzufügen</h3>
        <form class="addSet" method="POST" action="/addSet">
            <input type="hidden" name="workout_id"
                   value="<?= $params['workout']['id'] ?>">
            <input type="hidden" name="exercise_name" value="<?= $exercise['name'] ?>">
            <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
            <div class="flex column gap-20">
                <label class="flex column gap-10">
                    Satzanzahl
                    <input type="number" name="amount" min="1" max="10" required/>
                </label>
                <label class="flex column gap-10">
                    Wiederholungen
                    <input type="number" name="repetitions"
                           required/>
                </label>
                <label class="flex column gap-10">
                    Einheit
                    <input type="text" name="measure_unit"
                           required/>
                </label>
                <label class="flex column gap-10">
                    Pausenzeit (s)
                    <input type="text" name="rest_time"
                           required/>
                </label>
                <div class="flex justify-center">
                    <input class="addButton" type="image" alt="add" src="/img/add.svg"">
                </div>
            </div>
        </form>
    </div>
</div>

<div class="edit-container" id="edit-exercise-<?= $exercise['id'] ?>">
    <div class="wrapper">
        <div class="close">
            <img class="icon edit-icon" src="/img/close.svg" alt="edit-icon"/>
        </div>
        <div class="inner flex column gap-20">
            <div class="flex align-center">
                <form class="flex align-center" method="POST" action="/deleteExercise">
                    <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                    <input type="hidden" name="workout_id"
                           value="<?= $params['workout']['id'] ?>">
                    <input class="deleteButton w-15 h-15 relative bottom-3" type="image" alt="delete"
                           src="/img/close.svg">
                </form>
                <h3 class="m-0">Übung bearbeiten</h3>
            </div>
            <form class="flex gap-20 align-center" method="POST" action="/editExercise">
                <input type="hidden" name="workout_id"
                       value="<?= $params['workout']['id'] ?>">
                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                <label>
                    <input class="no-border no-padding no-radius" type="text" name="exercise_name"
                           value="<?= $exercise['name'] ?>">
                </label>
                <input class="saveButton w-20 h-20" type="image" alt="save" src="/img/save.svg">
            </form>
        </div>
    </div>
</div>