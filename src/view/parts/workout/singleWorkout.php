<div class="workout">
    <a href="/workout?workout_id=<?= $workout['id'] ?>">
        <p><?= $workout['name'] ?></p>
    </a>
    <div class="editButton" data-target="edit-workout-<?= $workout['id'] ?>"><img class="icon edit-icon"
                                                                                  src="/img/edit.png"
                                                                                  alt="edit-icon"/>
    </div>
    <?php include(__DIR__ . "/editWorkoutContainer.php") ?>
</div>