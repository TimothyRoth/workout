<div class="wrapper">
    <h2>Workouts</h2>
    <?php include_once(__DIR__ . "/parts/workout/addWorkout.php"); ?>
    <?php if (count($params) > 0) { ?>
        <div class="workouts flex column gap-m">
            <?php foreach ($params as $workout) {
                include(__DIR__ . "/parts/workout/singleWorkout.php");
            } ?>
        </div>
    <?php } ?>
</div>