<div class="wrapper">
    <div class="singleSessionView">
        <h2><?= $params['workout']['name'] ?></h2>
        <form class="addExercise addButton" method="POST" action="/addExercise">
            <label>
                <input type="hidden" name="workout_id" value="<?= $params['workout']['id'] ?>">
                <input name="exercise_name" placeholder="Neue Übung" type="text" required>
            </label>
            <input type="submit" value="+">
        </form>
        <?php if (count($params['exercises']) > 0) { ?>
            <div class="exercises">
                <?php foreach ($params['exercises'] as $exercise) { ?>
                    <div class="exercise">
                        <div class="top">
                            <p><?= $exercise['name'] ?></p>
                            <div class="editButton" data-target="edit-exercise-<?= $exercise['id'] ?>">Bearbeiten</div>
                        </div>
                        <div class="sets">
                            <?php if (!empty($exercise['sets'])) { ?>
                                <div class="set">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Wh</th>
                                            <th>Einheit</th>
                                            <th>Pause</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($exercise['sets'] as $set): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($set['repetitions']) ?></td>
                                                <td><?= htmlspecialchars($set['measure_unit']) ?></td>
                                                <td><?= htmlspecialchars($set['rest_time']) ?></td>
                                                <td>
                                                    <div class="editButton" data-target="edit-set-<?= $set['id'] ?>">
                                                        Bearbeiten
                                                    </div>
                                                </td>
                                            </tr>
                                            <div class="edit-container" id="edit-set-<?= $set['id'] ?>">
                                                <div class="close">Schließen</div>
                                                <form method="POST" action="/editSet">
                                                    <input type="hidden" name="set_id" value="<?= $set['id'] ?>">
                                                    <input type="hidden" name="workout_id"
                                                           value="<?= $params['workout']['id'] ?>">
                                                    <label>
                                                        <input type="number" name="repetitions"
                                                               value="<?= $set['repetitions'] ?>" required/>
                                                    </label>
                                                    <label>
                                                        <input type="text" name="measure_unit"
                                                               value="<?= $set['measure_unit'] ?>" required/>
                                                    </label>
                                                    <label>
                                                        <input type="text" name="rest_time"
                                                               value="<?= $set['rest_time'] ?>" required/>
                                                    </label>
                                                    <input type="submit" value="Speichern"/>
                                                </form>
                                                <form method="POST" action="/deleteSet">
                                                    <input type="hidden" name="set_id" value="<?= $set['id'] ?>">
                                                    <input type="hidden" name="workout_id"
                                                           value="<?= $params['workout']['id'] ?>">
                                                    <input type="submit" value="Satz entfernen">
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                            <div class="edit-container" id="edit-exercise-<?= $exercise['id'] ?>">
                                <div class="close">Schließen</div>
                                <form method="POST" action="/deleteExercise">
                                    <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                                    <input type="hidden" name="workout_id" value="<?= $params['workout']['id'] ?>">
                                    <input type="submit" value="Übung entfernen">
                                </form>
                                <form method="POST" action="/editExercise">
                                    <input type="hidden" name="workout_id" value="<?= $params['workout']['id'] ?>">
                                    <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                                    <label>
                                        <input type="text" name="exercise_name" value="<?= $exercise['name'] ?>">
                                    </label>
                                    <input type="submit" value="Speichern">
                                </form>
                                <form class="addSet addButton" method="POST" action="/addSet">
                                    <input type="hidden" name="workout_id" value="<?= $params['workout']['id'] ?>">
                                    <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                                    <label>
                                        <input type="number" name="repetitions" placeholder="Wiederholungen" required/>
                                    </label>
                                    <label>
                                        <input type="text" name="measure_unit" placeholder="Gewicht / Dauer" required/>
                                    </label>
                                    <label>
                                        <input type="text" name="rest_time" placeholder="Pausenzeit" required/>
                                    </label>
                                    <input type="submit" value="Satz hinzufügen">
                                </form>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <a class="backButton" href="/">Zurück</a>
</div>