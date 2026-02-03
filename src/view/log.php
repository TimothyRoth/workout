<div class="wrapper">

    <?php if (empty($params)) { ?>
        <div class="flex gap-10 align-center mt-20">
            <a class="flex" href="/">
                <img alt="back" src="/img/back.svg"/>
            </a>
            <h2 class="m-0">Keine Einträge gefunden.</h2>
        </div>
    <?php } else { ?>
        <div class="flex gap-10 align-center mt-20">
            <a class="flex" href="/">
                <img alt="back" src="/img/back.svg"/>
            </a>
            <h2 class="m-0">Logs</h2>
        </div>
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


                if ($previous !== null) {
                    if ($current > $previous) {
                        $currentToPrevClass = "high";
                    } elseif ($current < $previous) {
                        $currentToPrevClass = "low";
                    }
                }
                ?>

                <div class="logEntry pt-20">
                    <p><b>Workout: </b><?= htmlspecialchars($log['workout_name']) ?></p>
                    <p><b>Datum: </b><?= htmlspecialchars($log['created_at']) ?></p>
                    <p><b>Dauer: </b><?= htmlspecialchars($log['duration']) ?> Minuten</p>

                    <?php $logs = json_decode($log['workout_summary'], true, 512, JSON_THROW_ON_ERROR)["exercises"];
                    $logSize = count($logs);
                    if ($logSize > 0) { ?>
                        <div class="trigger-accordion">
                            <p class="trigger pb-20">Show Log</p>
                            <div class="trigger-container" id="workout_summary">

                                <?php foreach ($logs as $i => $exercise) { ?>
                                    <div <?php if ($i + 1 === $logSize) {
                                        echo "class='mb-20'";
                                    } ?>>
                                        <h5><?= $exercise['name'] ?></h5>
                                        <?php foreach ($exercise['sets'] as $j => $set) { ?>
                                            <div>
                                                <p><b><?= $j + 1 ?>. Satz</b></p>
                                                <p><b>Wiederholungen:</b> <?= $set['reps'] ?></p>
                                                <p><b>Einheit:</b> <?= $set['measureUnit'] ?></p>
                                                <p><b>Pause:</b> <?= $set['breaktime'] ?></p>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>

                    <p class="mt-0"><b>Workload: </b><?= htmlspecialchars($current) ?></p>
                    <div class="flex gap-10 column text-center">
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
