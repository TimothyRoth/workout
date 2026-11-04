<?php
/**
 * One exercise on the workout page: a collapsed row (name + one-line summary of
 * the sets), expandable to the set table and its actions. js/exercises.js keeps
 * the open state per workout and opens the exercise named in the URL hash.
 */
$exerciseId = (int)$exercise['id'];
?>
<details class="card exercise" id="exercise-<?= $exerciseId ?>" data-exercise-id="<?= $exerciseId ?>" data-sortable-item<?= $params['exercisesOpen'] ? ' open' : '' ?>>
    <summary class="exercise-summary">
        <span class="drag-handle" data-drag-handle aria-label="Übung verschieben"><?= icon('grip') ?></span>
        <span class="exercise-summary-text">
            <span class="exercise-name"><?= e($exercise['name']) ?></span>
            <span class="exercise-sets-summary"><?= e(exercise_summary($exercise['sets'])) ?></span>
        </span>
        <?= icon('chevron', 'exercise-chevron') ?>
    </summary>

    <div class="exercise-body">
        <?php if (empty($exercise['sets'])) { ?>
            <p class="hint">Noch keine Sätze.</p>
        <?php } else {
            include __DIR__ . '/../set/set.php';
        } ?>

        <div class="exercise-actions">
            <button class="btn btn-ghost btn-block" type="button" data-dialog="add-sets-<?= $exerciseId ?>"><?= icon('plus', 'icon-sm') ?> Sätze hinzufügen</button>
            <?php if (!empty($exercise['sets'])) { $lastSet = $exercise['sets'][array_key_last($exercise['sets'])]; ?>
                <form method="POST" action="/duplicateSet">
                    <input type="hidden" name="set_id" value="<?= e($lastSet['id']) ?>">
                    <button class="btn btn-ghost" type="submit" aria-label="Letzten Satz kopieren" title="Letzten Satz kopieren"><?= icon('copy', 'icon-sm') ?></button>
                </form>
            <?php } ?>
            <button class="btn btn-ghost" type="button" data-dialog="edit-exercise-<?= $exerciseId ?>" aria-label="Übung bearbeiten" title="Übung bearbeiten"><?= icon('edit', 'icon-sm') ?></button>
        </div>
    </div>
</details>

<?php
// The sheets live next to the <details>: content of a closed <details> is not rendered, a modal inside it would not show.
foreach ($exercise['sets'] as $set) {
    include __DIR__ . '/../set/setEditContainer.php';
}
include __DIR__ . '/exerciseEditContainer.php';
