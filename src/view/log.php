<div class="wrapper">
    <a class="mt-2 inline-block" href="/">Zurück</a>
    <?php if (empty($params)) { ?>
        <h2>Keine Einträge gefunden.</h2>
    <?php } else { ?>
        <h2>Logs</h2>
        <div class="entries">
            <?php foreach ($params as $log) {

                $current = $log['actual_workload'];
                $previous = $log['previous_workload'];
                $max = $log['max_workload'];

                $deltaMax = round(($current - $max) / $max * 100, 1);
                $deltaPrev = $previous !== null ? round(($current - $previous) / $previous * 100, 1) : null;

                if ($current === $max) {
                    $workoutClass = "high";
                } elseif ($current > $previous) {
                    $workoutClass = "medium";
                } else {
                    $workoutClass = "low";
                } ?>

                <div class="logEntry pt-2">
                    <p>
                        <b>Workout: </b><?= htmlspecialchars($log['workout_name']) ?>
                        <span class="<?= $workoutClass ?>">
                            <?= "<br>" ?>
                            <?php if ($deltaMax > 0) {
                                echo "+{$deltaMax}% vom Bestwert";
                            }
                            elseif ($deltaMax < 0) {
                                echo "{$deltaMax}% vom Bestwert";
                            }
                            else {
                                echo "<br>Bester Wert!";
                            } ?>
                            <?php if ($deltaPrev !== null) {
                                echo "<br>";
                                echo $deltaPrev > 0 ? "+{$deltaPrev}%" : "{$deltaPrev}%";
                                echo " vom vorherigen Wert";
                            } ?>
                        </span>
                    </p>
                    <p><b>Datum: </b><?= htmlspecialchars($log['created_at']) ?></p>
                    <p><b>Dauer: </b><?= htmlspecialchars($log['duration']) ?> Minuten</p>
                    <p><b>Workload: </b><?= htmlspecialchars($current) ?></p>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
