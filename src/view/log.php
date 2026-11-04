<?php
$logs = $params['logs'];
$histories = \App\History::byExercise($logs);

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
        $bestClass = 'badge-neutral';
    } elseif ($current > $best) {
        $bestLabel = 'Neuer Bestwert!';
        $bestClass = 'badge-gold';
    } elseif ($current == $best) {
        $bestLabel = 'Bestwert eingestellt';
        $bestClass = 'badge-neutral';
    } elseif ($best > 0) {
        $bestLabel = number_format(($current - $best) / $best * 100, 1, ',', '') . '% vom Bestwert';
        $bestClass = 'badge-down';
    } else {
        $bestLabel = 'Unter Bestwert';
        $bestClass = 'badge-down';
    }

    $prevLabel = null;
    $prevClass = 'badge-neutral';
    if ($prev !== null && $best !== null && $current != $best) {
        if ($prev > 0) {
            $delta = ($current - $prev) / $prev * 100;
            $prevLabel = ($delta >= 0 ? '+' : '') . number_format($delta, 1, ',', '') . '% zum letzten Mal';
        } else {
            $prevLabel = 'Letztes Mal: 0';
        }
        $prevClass = $current > $prev ? 'badge-up' : ($current < $prev ? 'badge-down' : 'badge-neutral');
    }

    return [$bestLabel, $bestClass, $prevLabel, $prevClass];
};
?>
<header class="topbar">
    <a class="icon-btn" href="/" aria-label="Zurück"><?= icon('back') ?></a>
    <h1>Logs</h1>
</header>

<?php if (empty($logs)) { ?>
    <p class="empty">Noch keine Einträge. Starte ein Workout, dann erscheint es hier.</p>
<?php } else { ?>
    <div class="stack">
        <?php foreach ($logs as $log) {
            [$bestLabel, $bestClass, $prevLabel, $prevClass] = $classify($log);
            ?>
            <article class="card log" id="log-<?= e($log['id']) ?>">
                <header class="card-header">
                    <div class="card-header-text">
                        <h2><?= e($log['workout_name']) ?><?php if ($log['workout_deleted']) { ?><span class="tag">gelöscht</span><?php } ?></h2>
                        <p class="hint"><?= e(local_datetime($log['created_at'])) ?></p>
                    </div>
                    <button class="icon-btn" type="button" data-dialog="edit-log-<?= e($log['id']) ?>" aria-label="Eintrag bearbeiten"><?= icon('edit') ?></button>
                </header>
                <?php include __DIR__ . '/parts/log/logEditContainer.php'; ?>

                <?php if (($log['notes'] ?? '') !== '') { ?>
                    <p class="log-notes"><?= icon('note', 'icon-sm') ?><span><?= e($log['notes']) ?></span></p>
                <?php } ?>

                <div class="stack">
                    <div class="stats">
                        <div class="stat"><b><?= e($log['duration']) ?></b><span>Minuten</span></div>
                        <div class="stat"><b><?= e(format_number($log['actual_workload'])) ?></b><span>Workload</span></div>
                    </div>
                    <div class="badges">
                        <span class="badge <?= e($bestClass) ?>"><?= e($bestLabel) ?></span>
                        <?php if ($prevLabel !== null) { ?>
                            <span class="badge <?= e($prevClass) ?>"><?= e($prevLabel) ?></span>
                        <?php } ?>
                    </div>
                </div>

                <?php if (count($log['exercises']) > 0) { ?>
                    <details class="disclosure">
                        <summary>Protokoll anzeigen <?= icon('chevron', 'icon-sm') ?></summary>
                        <div class="disclosure-body">
                            <?php foreach ($log['exercises'] as $exercise) {
                                $history = $histories[\App\History::key($log, $exercise)] ?? [];
                                ?>
                                <div>
                                    <h3><?= e($exercise['name']) ?></h3>
                                    <table class="sets">
                                        <thead>
                                        <tr>
                                            <th class="num">#</th>
                                            <th>Wdh.</th>
                                            <th>Last</th>
                                            <th>Pause</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($exercise['sets'] as $j => $set) { ?>
                                            <tr>
                                                <td class="num"><?= $j + 1 ?></td>
                                                <td><?= e($set['repetitions']) ?></td>
                                                <td class="unit"><?= e(set_label($set)) ?></td>
                                                <td><?= e($set['rest_time']) ?> s</td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                    <?php if (count($history) > 1) { ?>
                                        <details class="disclosure disclosure-nested">
                                            <summary>Verlauf (<?= count($history) ?>) <?= icon('chevron', 'icon-sm') ?></summary>
                                            <table class="sets history">
                                                <tbody>
                                                <?php foreach (array_slice($history, 0, 10) as $entry) { ?>
                                                    <tr<?= $entry['log_id'] === (int)$log['id'] ? ' class="is-current"' : '' ?>>
                                                        <td class="num"><?= e(local_datetime($entry['created_at'], 'd.m.y')) ?></td>
                                                        <td class="unit"><?= e(\App\History::setsLine($entry['sets'])) ?></td>
                                                        <td class="volume"><?= e(format_number($entry['volume'])) ?></td>
                                                    </tr>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </details>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </details>
                <?php } ?>
            </article>
        <?php } ?>
    </div>
<?php } ?>
