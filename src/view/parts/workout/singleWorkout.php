<div class="workout flex justify-space-between">
    <a class="pt-10 pb-10 m-0" href="/workout?workout_id=<?= $workout['id'] ?>">
        <p><?= $workout['name'] ?></p>
    </a>
    <div class="editButton" data-target="edit-workout-<?= $workout['id'] ?>"><img class="icon edit-icon"
                                                                                  src="/img/edit.svg"
                                                                                  alt="edit-icon"/>
    </div>
    <?php include(__DIR__ . "/editWorkoutContainer.php") ?>
</div>