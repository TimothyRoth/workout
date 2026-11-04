<?php $workout = $params['workout']; ?>
<header class="topbar">
    <a class="icon-btn" href="/" aria-label="Zurück"><?= icon('back') ?></a>
    <h1><?= e($workout['name']) ?></h1>
    <button class="icon-btn" type="button" data-dialog="edit-workout-<?= e($workout['id']) ?>" aria-label="Workout bearbeiten"><?= icon('edit') ?></button>
</header>
<?php include __DIR__ . '/parts/workout/editWorkoutContainer.php'; ?>

<div class="stack-lg">
    <button class="btn btn-primary btn-block btn-lg" type="button" id="start-workout"
            data-workout-id="<?= e($workout['id']) ?>" data-workout-updated="<?= e($workout['updated_at']) ?>">
        <?= icon('play') ?> Workout starten
    </button>

    <form class="card inline-form" method="POST" action="/addExercise">
        <input type="hidden" name="workout_id" value="<?= e($workout['id']) ?>">
        <input class="input" name="exercise_name" type="text" placeholder="Neue Übung" maxlength="100" autocomplete="off" required>
        <button class="icon-btn icon-btn-accent" type="submit" aria-label="Übung hinzufügen"><?= icon('plus') ?></button>
    </form>

    <?php if (count($workout['exercises']) === 0) { ?>
        <p class="empty">Noch keine Übungen. Füge oben die erste hinzu.</p>
    <?php } else { ?>
        <div class="stack" data-sortable="exercises" data-parent-id="<?= e($workout['id']) ?>">
            <?php foreach ($workout['exercises'] as $exercise) {
                include __DIR__ . '/parts/exercise/exercise.php';
            } ?>
        </div>
    <?php } ?>
</div>

<?php include __DIR__ . '/parts/workout/workoutSessionMold.php'; ?>
