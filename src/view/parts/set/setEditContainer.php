<div class="edit-container" id="edit-set-<?= $set['id'] ?>">
    <div class="wrapper">
        <div class="close">
            <img class="icon edit-icon" src="/img/close.png" alt="edit-icon"/>
        </div>
        <div class="inner flex column gap-m">
            <h3>Satz bearbeiten</h3>
            <form method="POST" action="/editSet">
                <div class="flex column gap-m">
                    <input type="hidden" name="exercise_name" value="<?= $exercise['name'] ?>">
                    <input type="hidden" name="set_id" value="<?= $set['id'] ?>">
                    <input type="hidden" name="workout_id" value="<?= $params['workout']['id'] ?>">

                    <div class="flex gap-m column">
                        <label>
                            <input type="number" name="repetitions" placeholder="Wiederholungen" value="<?= $set['repetitions'] ?>" required/>
                        </label>
                        <label>
                            <input type="text" name="measure_unit" placeholder="Einheit" value="<?= $set['measure_unit'] ?>" required/>
                        </label>
                        <label>
                            <input type="text" name="rest_time" placeholder="Pausenzeit" value="<?= $set['rest_time'] ?>" required/>
                        </label>
                        <input class="button saveButton" type="submit" value="Änderungen speichern">
                    </div> <!-- closes .flex.gap-m.column -->
                </div> <!-- ✅ this one was missing (closes .flex.column.gap-m inside form) -->
            </form>
        </div>
        <form method="POST" action="/deleteSet">
            <input type="hidden" name="exercise_name" value="<?= $exercise['name'] ?>">
            <input type="hidden" name="set_id" value="<?= $set['id'] ?>">
            <input type="hidden" name="workout_id" value="<?= $params['workout']['id'] ?>">
            <input class="deleteButton button" type="submit" value="Satz löschen">
        </form>
    </div>
</div>
