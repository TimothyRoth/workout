<div class="wrapper">
    <h3>Trainingseinheiten</h3>
    <form class="addWorkout addButton" method="POST" action="/addWorkout">
        <label>
            <input name="workout_name" type="text" placeholder="Neues Workout" required>
        </label>
        <input type="submit" value="+">
    </form>
    <?php if (count($params) > 0) { ?>
        <div class="workouts">
            <?php foreach ($params as $workout) { ?>
                <div class="workout">
                    <a href="/workout?workout_id=<?= $workout['id'] ?>">
                        <p><?= $workout['name'] ?></p>
                    </a>
                    <div class="editButton" data-target="edit-workout-<?= $workout['id'] ?>">Bearbeiten</div>
                    <div class="edit-container" id="edit-workout-<?= $workout['id'] ?>">
                        <form method="POST" action="/deleteWorkout">
                            <input type="hidden" name="workout_id" value="<?= $workout['id'] ?>">
                            <input type="submit" value="Workout entfernen">
                        </form>
                        <form method="POST" action="/editWorkout">
                            <input type="hidden" name="workout_id" value="<?= $workout['id'] ?>">
                            <input type="text" name="workout_name" value="<?= $workout['name'] ?>">
                            <input type="submit" value="Speichern">
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>