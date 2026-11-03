<div class="wrapper">
    <h2>Workouts</h2>
    <?php include __DIR__ . "/parts/workout/addWorkout.php"; ?>
    <?php if (count($params['workouts']) > 0) { ?>
        <div class="workouts flex column gap-10">
            <?php foreach ($params['workouts'] as $workout) {
                include __DIR__ . "/parts/workout/singleWorkout.php";
            } ?>
        </div>
    <?php } ?>
    <a class="mt-40 inline-block" href="/logs">Logs</a>
</div>
