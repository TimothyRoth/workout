<div class="wrapper">
    <a class="mt-2 inline-block" href="/">Zurück</a>
    <?php if(empty($params)) { ?>
        <h2>Keine Einträge gefunden.</h2>
    <?php } else { ?>
        <h2>Logs</h2>
        <div class="entries">
            <?php foreach($params as $log) { ?>
                <div class="logEntry">
                    <p><b>Workout: </b><?= htmlspecialchars($log['workout_name']) ?></p>
                    <p><b>Datum: </b><?= htmlspecialchars($log['created_at']) ?></p>
                    <p><b>Dauer: </b><?= htmlspecialchars($log['duration']) ?> Minuten</p>
                    <p><b>Workload: </b><?= htmlspecialchars($log['workload']) ?></p>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>