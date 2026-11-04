<table class="sets" data-sortable="sets" data-parent-id="<?= e($exercise['id']) ?>">
    <thead>
    <tr>
        <th class="num">#</th>
        <th>Wdh.</th>
        <th>Last</th>
        <th>Pause</th>
        <th class="actions"></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($exercise['sets'] as $i => $set) { ?>
        <tr data-set-id="<?= e($set['id']) ?>" data-sortable-item>
            <td class="num drag-handle" data-drag-handle aria-label="Satz <?= $i + 1 ?> verschieben"><?= icon('grip', 'icon-sm') ?><span><?= $i + 1 ?></span></td>
            <td><?= e($set['repetitions']) ?></td>
            <td class="unit"><?= e(set_label($set)) ?></td>
            <td><?= e($set['rest_time']) ?> s</td>
            <td class="actions">
                <button class="icon-btn" type="button" data-dialog="edit-set-<?= e($set['id']) ?>" aria-label="Satz <?= $i + 1 ?> bearbeiten"><?= icon('edit', 'icon-sm') ?></button>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
