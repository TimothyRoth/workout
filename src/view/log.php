<?php
$logs = $params['logs'];

/**
 * Classify a log entry against its own history (only entries before it).
 * Returns [bestLabel, bestClass, prevLabel|null, prevClass].
 */
$classify = static function (array $log): array {
    $current = (float)$log['actual_workload'];
    $best = $log['best_before'] === null ? null : (float)$log['best_before'];
    $prev = $log['previous_workload'] === null ? null : (float)$log['previous_workload'];

    if ($best === null) {
        $bestLabel = 'Erster Eintrag';
        $bestClass = 'medium';
    } elseif ($current > $best) {
        $bestLabel = 'Neuer Bestwert!';
        $bestClass = 'gold';
    } elseif ($current == $best) {
        $bestLabel = 'Bestwert eingestellt';
        $bestClass = 'medium';
    } elseif ($best > 0) {
        $bestLabel = number_format(($current - $best) / $best * 100, 1, ',', '') . '% vom Bestwert';
        $bestClass = 'low';
    } else {
        $bestLabel = 'Unter Bestwert';
        $bestClass = 'low';
    }

    $prevLabel = null;
    $prevClass = 'medium';
    if ($prev !== null && $best !== null && $current != $best) {
        if ($prev > 0) {
            $delta = ($current - $prev) / $prev * 100;
            $prevLabel = ($delta >= 0 ? '+' : '') . number_format($delta, 1, ',', '') . '% vom vorherigen Wert';
        } else {
            $prevLabel = 'Vorheriger Wert: 0';
        }
        $prevClass = $current > $prev ? 'high' : ($current < $prev ? 'low' : 'medium');
    }

    return [$bestLabel, $bestClass, $prevLabel, $prevClass];
};
?>
<div class="wrapper">
    <div class="flex gap-10 align-center mt-20">
        <a class="flex" href="/"><img alt="zurück" src="/img/back.svg"/></a>
        <h2 class="m-0"><?= empty($logs) ? 'Keine Einträge gefunden.' : 'Logs' ?></h2>
    </div>

    <?php if (!empty($logs)) { ?>
        <div class="entries">
            <?php foreach ($logs as $log) {
                [$bestLabel, $bestClass, $prevLabel, $prevClass] = $classify($log);

                $summary = json_decode((string)$log['workout_summary'], true);
                $exercises = is_array($summary) && isset($summary['exercises']) && is_array($summary['exercises'])
                    ? array_values($summary['exercises'])
                    : [];
                $exerciseCount = count($exercises);
                ?>
                <div class="logEntry pt-20">
                    <p><b>Workout: </b><?= e($log['workout_name']) ?><?php if ($log['workout_deleted']) { ?> <span class="font-weight-300">(gelöscht)</span><?php } ?></p>
                    <p><b>Datum: </b><?= e(local_datetime($log['created_at'])) ?></p>
                    <p><b>Dauer: </b><?= e($log['duration']) ?> Minuten</p>

                    <?php if ($exerciseCount > 0) { ?>
                        <div class="trigger-accordion">
                            <p class="trigger pb-20">Show Log</p>
                            <div class="trigger-container">
                                <?php foreach ($exercises as $i => $exercise) { ?>
                                    <div<?= $i + 1 === $exerciseCount ? ' class="mb-20"' : '' ?>>
                                        <h5><?= e($exercise['name'] ?? '') ?></h5>
                                        <?php foreach (($exercise['sets'] ?? []) as $j => $set) { ?>
                                            <div>
                                                <p><b><?= $j + 1 ?>. Satz</b></p>
                                                <p><b>Wiederholungen:</b> <?= e($set['reps'] ?? '') ?></p>
                                                <p><b>Einheit:</b> <?= e($set['measureUnit'] ?? '') ?></p>
                                                <p><b>Pause:</b> <?= e($set['breaktime'] ?? '') ?></p>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>

                    <p class="mt-0"><b>Workload: </b><?= e(rtrim(rtrim(number_format((float)$log['actual_workload'], 2, ',', '.'), '0'), ',')) ?></p>
                    <div class="flex gap-10 column text-center">
                        <span class="<?= e($bestClass) ?>"><?= e($bestLabel) ?></span>
                        <?php if ($prevLabel !== null) { ?>
                            <span class="<?= e($prevClass) ?>"><?= e($prevLabel) ?></span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
