<?php /** Correct a logged session: duration, notes, reps and loads of its sets; delete it. */ ?>
<dialog class="sheet" id="edit-log-<?= e($log['id']) ?>">
    <div class="sheet-header">
        <h2>Eintrag bearbeiten</h2>
        <button class="icon-btn" type="button" data-close aria-label="Schließen"><?= icon('close') ?></button>
    </div>
    <form class="sheet-body" method="POST" action="/editLog">
        <input type="hidden" name="log_id" value="<?= e($log['id']) ?>">
        <p class="hint"><?= e($log['workout_name']) ?> · <?= e(local_datetime($log['created_at'])) ?></p>
        <label class="field" for="log-<?= e($log['id']) ?>-duration">
            <span>Dauer (Minuten)</span>
            <?= stepper('<input class="input" id="log-' . e($log['id']) . '-duration" type="number" name="duration" min="0" max="1440" inputmode="numeric" data-step="5" value="' . e($log['duration']) . '" required>') ?>
        </label>
        <?php foreach ($log['exercises'] as $exercise) { ?>
            <div class="log-edit-exercise">
                <h3><?= e($exercise['name']) ?></h3>
                <?php foreach ($exercise['sets'] as $j => $set) {
                    $unit = \App\Unit::tryFrom((string)$set['unit']) ?? \App\Unit::Kg; ?>
                    <div class="log-edit-set">
                        <span class="num"><?= $j + 1 ?></span>
                        <input class="input" type="number" name="sets[<?= e($set['id']) ?>][reps]" min="0" max="10000" inputmode="numeric" value="<?= e($set['repetitions']) ?>" aria-label="Wiederholungen Satz <?= $j + 1 ?>" required>
                        <?php if ($unit->needsLoad()) { ?>
                            <input class="input" type="number" name="sets[<?= e($set['id']) ?>][load]" min="0" step="any" inputmode="decimal" value="<?= e($set['load'] === null ? '' : format_number($set['load'])) ?>" aria-label="Last Satz <?= $j + 1 ?>">
                            <span class="hint"><?= e($unit->symbol()) ?></span>
                        <?php } else { ?>
                            <span class="hint log-edit-unit"><?= e($unit->label()) ?></span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
        <label class="field">
            <span>Notiz</span>
            <textarea class="input textarea" name="notes" rows="2" maxlength="1000"><?= e($log['notes'] ?? '') ?></textarea>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Speichern</button>
    </form>
    <form class="sheet-footer" method="POST" action="/deleteLog" data-confirm="Diesen Eintrag endgültig löschen?">
        <input type="hidden" name="log_id" value="<?= e($log['id']) ?>">
        <button class="btn btn-danger btn-block" type="submit"><?= icon('trash', 'icon-sm') ?> Eintrag löschen</button>
    </form>
</dialog>
