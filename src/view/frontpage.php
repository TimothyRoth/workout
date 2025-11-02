<div class="wrapper">
    <h3>Workouts</h3>
    <form class="addWorkout addButton" method="POST" action="/addWorkout">
        <label>
            <input name="workout_name" type="text" placeholder="Neues Workout" required>
        </label>
    </form>
    <?php if (count($params) > 0) { ?>
        <div class="workouts flex column gap-m">
            <?php foreach ($params as $workout) { ?>
                <div class="workout">
                    <a href="/workout?workout_id=<?= $workout['id'] ?>">
                        <p><?= $workout['name'] ?></p>
                    </a>
                    <div class="editButton" data-target="edit-workout-<?= $workout['id'] ?>"><img class="icon edit-icon"
                                                                                                  src="/img/edit.png"
                                                                                                  alt="edit-icon"/>
                    </div>
                    <div class="edit-container" id="edit-workout-<?= $workout['id'] ?>">
                        <div class="wrapper">
                            <div class="close"><img class="icon edit-icon" src="/img/close.png" alt="edit-icon"/></div>
                            <div class="flex gap-m column">
                                <form method="POST" action="/editWorkout">
                                    <input type="hidden" name="workout_id" value="<?= $workout['id'] ?>">
                                    <label>
                                        <input type="text" name="workout_name" value="<?= $workout['name'] ?>">
                                    </label>
                                </form>
                                <form method="POST" action="/deleteWorkout">
                                    <input type="hidden" name="workout_id" value="<?= $workout['id'] ?>">
                                    <input class="deleteButton button" type="submit" value="Workout löschen">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>