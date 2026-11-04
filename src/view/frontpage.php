<header class="topbar">
    <h1>Workouts</h1>
    <a class="icon-btn" href="/logs" aria-label="Logs" title="Logs"><?= icon('history') ?></a>
</header>

<div class="stack-lg">
    <?php include __DIR__ . '/parts/workout/addWorkout.php'; ?>

    <?php if (count($params['workouts']) === 0) { ?>
        <p class="empty">Noch keine Workouts. Lege oben dein erstes an.</p>
    <?php } else { ?>
        <ul class="list">
            <?php foreach ($params['workouts'] as $workout) {
                include __DIR__ . '/parts/workout/singleWorkout.php';
            } ?>
        </ul>
    <?php } ?>

    <?php include __DIR__ . '/parts/installCard.php'; ?>
</div>
