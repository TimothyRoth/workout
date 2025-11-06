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
            <?php foreach ($params['exercises'] as $exercise) {
                include(__DIR__ . "/parts/exercise/exercise.php");
            } ?>
        </div>
    <?php } ?>
</div>
</div>