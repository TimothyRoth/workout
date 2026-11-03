<div class="workout flex justify-space-between">
    <a class="pt-10 pb-10 m-0" href="/workout?workout_id=<?= e($workout['id']) ?>">
        <p><?= e($workout['name']) ?></p>
    </a>
    <div class="editButton" data-target="edit-workout-<?= e($workout['id']) ?>">
        <img class="icon edit-icon" src="/img/edit.svg" alt="bearbeiten"/>
    </div>
    <?php include __DIR__ . "/editWorkoutContainer.php" ?>
</div>
