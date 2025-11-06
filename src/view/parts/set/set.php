<table>
    <thead>
    <tr>
        <th>Wh</th>
        <th>Einheit</th>
        <th>Pause</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($exercise['sets'] as $set) { ?>
        <tr>
            <td><?= htmlspecialchars($set['repetitions']) ?></td>
            <td><?= htmlspecialchars($set['measure_unit']) ?></td>
            <td><?= htmlspecialchars($set['rest_time']) ?></td>
            <td>
                <div class="editButton" data-target="edit-set-<?= $set['id'] ?>">
                    <img class="icon edit-icon"
                         src="/img/edit.png"
                         alt="edit-icon"/>
                </div>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
