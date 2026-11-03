<table class="sets">
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
        <tr data-set-id="<?= e($set['id']) ?>">
            <td class="num"><?= $i + 1 ?></td>
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
