<div class="exercise" id="<?= $exercise['name'] ?>">
    <div class="top">
        <h3 class="exerciseName"><?= $exercise['name'] ?></h3>
        <div class="editButton" data-target="edit-exercise-<?= $exercise['id'] ?>"><img
                    class="icon edit-icon" src="/img/edit.png" alt="edit-icon"/></div>
    </div>
    <div class="sets">
        <?php
        if (!empty($exercise['sets'])) {
            include(__DIR__ . "/../set/set.php");
        }

        foreach ($exercise['sets'] as $set) {
            include(__DIR__ . "/../set/setEditContainer.php");
        }
        ?>

    <div class="button addButton editButton" data-target="edit-sets-<?= $exercise['id'] ?>">+</div>

    <?php include(__DIR__ . "/exerciseEditContainer.php"); ?>
</div>
