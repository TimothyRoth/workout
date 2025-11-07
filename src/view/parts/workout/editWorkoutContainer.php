<div class="edit-container" id="edit-workout-<?= $workout['id'] ?>">
    <div class="wrapper">
        <div class="close"><img class="icon edit-icon" src="/img/close.png" alt="edit-icon"/></div>
        <div class="inner flex gap-m column">
            <h3>Workout bearbeiten</h3>
            <form method="POST" action="/editWorkout">
                <input type="hidden" name="workout_id" value="<?= $workout['id'] ?>">
                <label>
                    <input type="text" name="workout_name" placeholder="Name" value="<?= $workout['name'] ?>" required>
                </label>
            </form>
            <form method="POST" action="/deleteWorkout">
                <input type="hidden" name="workout_id" value="<?= $workout['id'] ?>">
                <input class="deleteButton button" type="submit" value="Workout löschen">
            </form>
        </div>
    </div>
</div>
