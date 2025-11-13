<table>
    <thead>
    <tr>
        <th>Wh</th>
        <th>Eh</th>
        <th>P</th>
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
                         src="/img/edit_white.svg"
                         alt="edit-icon"/>
                </div>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
