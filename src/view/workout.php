<div class="wrapper">
    <div class="singleSessionView">
        <div class="flex gap-s align-center">
            <a class="backButton" href="/"><img class="icon back-icon"
                                                src="/img/back.png"
                                                alt="back-icon"/></a>
            <h3 class=""><?= $params['workout']['name'] ?></h3>
        </div>
        <form class="addExercise" method="POST" action="/addExercise">
            <label>
                <input type="hidden" name="workout_id" value="<?= $params['workout']['id'] ?>">
                <input name="exercise_name" placeholder="Neue Übung" type="text" required>
            </label>
        </form>
        <?php if (count($params['exercises']) > 0) { ?>
        <div class="exercises flex column gap-m">
            <?php foreach ($params['exercises'] as $exercise) { ?>
            <div class="exercise" id="<?= $exercise['name'] ?>">
                <div class="top">
                    <h3 class="exerciseName"><?= $exercise['name'] ?></h3>
                    <div class="editButton" data-target="edit-exercise-<?= $exercise['id'] ?>"><img
                                class="icon edit-icon" src="/img/edit.png" alt="edit-icon"/></div>
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
                                <?php foreach ($exercise['sets'] as $set) { ?>
                                    <tr>
                                        <td><?= htmlspecialchars($set['repetitions']) ?></td>
                                        <td><?= htmlspecialchars($set['measure_unit']) ?></td>
                                        <td><?= htmlspecialchars($set['rest_time']) ?></td>
                                        <td>
                                            <div class="editButton" data-target="edit-set-<?= $set['id'] ?>">
                                                <img class="icon edit-icon"
                                                     src="/img/edit.png"
                                                     alt="edit-icon"/>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                    <?php foreach ($exercise['sets'] as $set) { ?>
                    <div class="edit-container" id="edit-set-<?= $set['id'] ?>">
                        <div class="wrapper">
                            <div class="close"><img class="icon edit-icon" src="/img/close.png"
                                                    alt="edit-icon"/></div>
                            <div class="flex column gap-m">
                                <form method="POST" action="/editSet">
                                    <div class="flex column gap-m">
                                        <input type="hidden" name="exercise_name" value="<?= $exercise['name'] ?>">
                                        <input type="hidden" name="set_id"
                                               value="<?= $set['id'] ?>">
                                        <input type="hidden" name="workout_id"
                                               value="<?= $params['workout']['id'] ?>">
                                        <div class="flex gap-m column">
                                            <label>
                                                <input type="number" name="repetitions"
                                                       value="<?= $set['repetitions'] ?>" required/>
                                            </label>
                                            <label>
                                                <input type="text" name="measure_unit"
                                                       value="<?= $set['measure_unit'] ?>"
                                                       required/>
                                            </label>
                                            <label>
                                                <input type="text" name="rest_time"
                                                       value="<?= $set['rest_time'] ?>" required/>
                                            </label>
                                            <input class="button saveButton" type="submit"
                                                   value="Änderungen speichern">
                                        </div>
                                </form>
                            </div>
                            <form method="POST" action="/deleteSet">
                                <input type="hidden" name="exercise_name" value="<?= $exercise['name'] ?>">
                                <input type="hidden" name="set_id"
                                       value="<?= $set['id'] ?>">
                                <input type="hidden" name="workout_id"
                                       value="<?= $params['workout']['id'] ?>">
                                <input class="deleteButton button"
                                       type="submit"
                                       value="Satz löschen">
                            </form>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <div class="edit-container" id="edit-exercise-<?= $exercise['id'] ?>">
                    <div class="wrapper">
                        <div class="close">
                            <img class="icon edit-icon" src="/img/close.png" alt="edit-icon"/>
                        </div>
                        <div class="flex column gap-m">
                            <form method="POST" action="/editExercise">
                                <input type="hidden" name="workout_id"
                                       value="<?= $params['workout']['id'] ?>">
                                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                                <label>
                                    <input type="text" name="exercise_name"
                                           value="<?= $exercise['name'] ?>">
                                </label>
                            </form>
                            <form class="addSet" method="POST" action="/addSet">
                                <input type="hidden" name="workout_id"
                                       value="<?= $params['workout']['id'] ?>">
                                <input type="hidden" name="exercise_name" value="<?= $exercise['name'] ?>">
                                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                                <div class="flex column gap-m">
                                    <label>
                                        <input type="number" name="repetitions" placeholder="Wiederholungen"
                                               required/>
                                    </label>
                                    <label>
                                        <input type="text" name="measure_unit" placeholder="Gewicht / Dauer"
                                               required/>
                                    </label>
                                    <label>
                                        <input type="text" name="rest_time" placeholder="Pausenzeit"
                                               required/>
                                    </label>
                                    <label>
                                        <input type="text" name="amount" min="1" max="10" value="1"/>
                                    </label>
                                    <input class="button addButton" type="submit" value="Satz hinzufügen">
                                </div>
                            </form>
                            <form method="POST" action="/deleteExercise">
                                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                                <input type="hidden" name="workout_id"
                                       value="<?= $params['workout']['id'] ?>">
                                <input class="deleteButton button" type="submit" value="Übung Löschen">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
    </div>
    <?php } ?>
</div>
</div>