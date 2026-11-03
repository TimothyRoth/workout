<?php $workout = $params['workout']; ?>
<div class="wrapper">
    <div class="singleWorkoutView">
        <div class="flex gap-10 align-center">
            <a class="backButton" href="/"><img class="icon back-icon" src="/img/back.svg" alt="zurück"/></a>
            <h3 class="workoutTitle"><?= e($workout['name']) ?></h3>
            <div class="startWorkout ml-auto" data-workout-id="<?= e($workout['id']) ?>" data-workout-updated="<?= e($workout['updated_at']) ?>">
                <img src="/img/start.svg" alt="Workout starten" class="icon start-icon"/>
            </div>
        </div>
        <form class="addExercise" method="POST" action="/addExercise">
            <label>
                <input type="hidden" name="workout_id" value="<?= e($workout['id']) ?>">
                <input class="no-border no-padding no-radius" name="exercise_name" placeholder="Neue Übung" type="text" maxlength="100" required>
            </label>
        </form>
        <?php if (count($workout['exercises']) > 0) { ?>
            <div class="exercises flex column gap-20">
                <?php foreach ($workout['exercises'] as $exercise) {
                    include __DIR__ . "/parts/exercise/exercise.php";
                } ?>
            </div>
        <?php } ?>
    </div>
</div>

<?php include __DIR__ . "/parts/workout/workoutSessionMold.php" ?>
