<div class="exercise" id="exercise-<?= e($exercise['id']) ?>" data-exercise-id="<?= e($exercise['id']) ?>">
    <div class="top">
        <h3 class="exerciseName"><?= e($exercise['name']) ?></h3>
        <div class="editButton" data-target="edit-exercise-<?= e($exercise['id']) ?>">
            <img class="icon edit-icon" src="/img/edit.svg" alt="bearbeiten"/>
        </div>
    </div>
    <div class="sets">
        <?php
        if (!empty($exercise['sets'])) {
            include __DIR__ . "/../set/set.php";
        }

        foreach ($exercise['sets'] as $set) {
            include __DIR__ . "/../set/setEditContainer.php";
        }
        ?>

        <div class="editButton addButton flex justify-center mt-20" data-target="edit-sets-<?= e($exercise['id']) ?>">
            <img src="/img/add.svg" alt="Satz hinzufügen"/>
        </div>

        <?php include __DIR__ . "/exerciseEditContainer.php"; ?>
    </div>
</div>
