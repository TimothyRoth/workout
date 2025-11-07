<div class="edit-container" id="edit-exercise-<?= $exercise['id'] ?>">
    <div class="wrapper">
        <div class="close">
            <img class="icon edit-icon" src="/img/close.png" alt="edit-icon"/>
        </div>
        <div class="inner flex column gap-m">
            <h3>Übung bearbeiten</h3>
            <form method="POST" action="/editExercise">
                <input type="hidden" name="workout_id"
                       value="<?= $params['workout']['id'] ?>">
                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                <label>
                    <input type="text" name="exercise_name"
                           value="<?= $exercise['name'] ?>">
                </label>
            </form>
            <form method="POST" action="/deleteExercise">
                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                <input type="hidden" name="workout_id"
                       value="<?= $params['workout']['id'] ?>">
                <input class="deleteButton button" type="submit" value="Übung Löschen">
            </form>
            <h3>Satz hinzufügen</h3>
            <form class="addSet" method="POST" action="/addSet">
                <input type="hidden" name="workout_id"
                       value="<?= $params['workout']['id'] ?>">
                <input type="hidden" name="exercise_name" value="<?= $exercise['name'] ?>">
                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                <div class="flex column gap-m">
                    <label>
                        <input type="number" name="amount" min="1" max="10" placeholder="Satzanzahl" required/>
                    </label>
                    <label>
                        <input type="number" name="repetitions" placeholder="Wiederholungen"
                               required/>
                    </label>
                    <label>
                        <input type="text" name="measure_unit" placeholder="Einheit"
                               required/>
                    </label>
                    <label>
                        <input type="text" name="rest_time" placeholder="Pausenzeit"
                               required/>
                    </label>
                    <input class="button addButton" type="submit" value="Satz hinzufügen">
                </div>
            </form>
        </div>
    </div>
</div>