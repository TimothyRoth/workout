<li class="list-item">
    <a class="list-link" href="/workout?workout_id=<?= e($workout['id']) ?>">
        <span class="title"><?= e($workout['name']) ?></span>
        <?= icon('chevron') ?>
    </a>
    <button class="icon-btn" type="button" data-dialog="edit-workout-<?= e($workout['id']) ?>" aria-label="Workout bearbeiten"><?= icon('edit') ?></button>
    <?php include __DIR__ . '/editWorkoutContainer.php'; ?>
</li>
