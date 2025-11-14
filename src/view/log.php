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
                $deltaPrev = $previous !== null ? round(($current - $previous) / $previous * 100, 2) : null;

                $currentToPrevClass = "medium";
                $currentToMaxClass = "medium";

                if ($current >= $max && ($previous === null || $current > $previous)) {
                    $currentToMaxClass = "gold";
                } elseif ($current < $max) {
                    $currentToMaxClass = "low";
                }


                if($previous !== null) {
                    if($current > $previous) {
                        $currentToPrevClass = "high";
                    } elseif ($current < $previous) {
                        $currentToPrevClass = "low";
                    }
                }
              ?>

                <div class="logEntry pt-2">
                    <p><b>Workout: </b><?= htmlspecialchars($log['workout_name']) ?></p>
                    <p><b>Datum: </b><?= htmlspecialchars($log['created_at']) ?></p>
                    <p><b>Dauer: </b><?= htmlspecialchars($log['duration']) ?> Minuten</p>
                    <div class="trigger-accordion">
                        <p class="trigger">Show Log</p>
                        <div class="trigger-container" id="workout_summary">
                            <?php $exercises = json_decode($log['workout_summary'], true)["exercises"];
                                foreach ($exercises as $exercise) { ?>
                                    <div>
                                        <h5>Übung: <?= $exercise['name'] ?></h5>
                                        <?php foreach ($exercise['sets'] as $index => $set) { ?>
                                            <div>
                                                <p><b><?= $index + 1 ?>. Satz</b></p>
                                                <p><b>Wiederholungen:</b> <?= $set['reps'] ?></p>
                                                <p><b>Einheit:</b> <?= $set['measureUnit'] ?></p>
                                                <p><b>Pause:</b> <?= $set['breaktime'] ?></p>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php }
                            ?>
                        </div>
                    </div>
                    <p><b>Workload: </b><?= htmlspecialchars($current) ?></p>
                    <div class="flex gap-s column text-center">
                        <span class="<?= $currentToMaxClass ?>">
                        <?php if ($deltaMax >= 0 && $current === $previous) {
                            echo "+{$deltaMax}% vom Bestwert";
                        } elseif ($deltaMax < 0) {
                            echo "{$deltaMax}% vom Bestwert";
                        } else {
                            echo "Neuer Bestwert!";
                        } ?>

                        <?php if ($deltaPrev !== null) { ?>
                        </span>
                        <br>
                        <span class="<?= $currentToPrevClass ?>">
                        <?= $deltaPrev >= 0 ? "+{$deltaPrev}%" : "{$deltaPrev}%" ?> vom vorherigen Wert
                        </span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
