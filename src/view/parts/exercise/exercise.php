<article class="card exercise" id="exercise-<?= e($exercise['id']) ?>" data-exercise-id="<?= e($exercise['id']) ?>">
    <header class="card-header">
        <h2><?= e($exercise['name']) ?></h2>
        <button class="icon-btn" type="button" data-dialog="edit-exercise-<?= e($exercise['id']) ?>" aria-label="Übung bearbeiten"><?= icon('edit') ?></button>
    </header>

    <?php if (empty($exercise['sets'])) { ?>
        <p class="hint">Noch keine Sätze.</p>
    <?php } else {
        include __DIR__ . '/../set/set.php';
    } ?>

    <button class="btn btn-ghost btn-block" type="button" data-dialog="add-sets-<?= e($exercise['id']) ?>"><?= icon('plus', 'icon-sm') ?> Sätze hinzufügen</button>

    <?php
    foreach ($exercise['sets'] as $set) {
        include __DIR__ . '/../set/setEditContainer.php';
    }
    include __DIR__ . '/exerciseEditContainer.php';
    ?>
</article>
