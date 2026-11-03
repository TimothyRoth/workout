<table>
    <thead>
    <tr>
        <th>Wh</th>
        <th>Eh</th>
        <th>P(s)</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($exercise['sets'] as $set) { ?>
        <tr data-set-id="<?= e($set['id']) ?>">
            <td><?= e($set['repetitions']) ?></td>
            <td><?= e($set['measure_unit']) ?></td>
            <td><?= e($set['rest_time']) ?></td>
            <td>
                <div class="editButton" data-target="edit-set-<?= e($set['id']) ?>">
                    <img class="icon edit-icon" src="/img/edit.svg" alt="bearbeiten"/>
                </div>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
