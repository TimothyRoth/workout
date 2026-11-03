<div class="edit-container" id="edit-set-<?= e($set['id']) ?>">
    <div class="wrapper">
        <div class="close">
            <img class="icon edit-icon" src="/img/close.svg" alt="schließen"/>
        </div>
        <div class="inner flex column gap-20">
            <div class="flex align-center">
                <form class="flex" method="POST" action="/deleteSet">
                    <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
                    <input class="deleteButton w-15 h-15 relative bottom-3" type="image" alt="löschen" src="/img/close.svg">
                </form>
                <h3>Satz bearbeiten</h3>
            </div>
            <form method="POST" action="/editSet">
                <div class="flex column gap-20">
                    <input type="hidden" name="set_id" value="<?= e($set['id']) ?>">
                    <div class="flex gap-20 column">
                        <label class="flex column gap-10">
                            Wiederholungen
                            <input type="number" name="repetitions" min="0" max="10000" inputmode="numeric" value="<?= e($set['repetitions']) ?>" required/>
                        </label>
                        <label class="flex column gap-10">
                            Einheit
                            <input type="text" name="measure_unit" maxlength="30" value="<?= e($set['measure_unit']) ?>" required/>
                        </label>
                        <label class="flex column gap-10">
                            Pausenzeit (s)
                            <input type="number" name="rest_time" min="0" max="3600" inputmode="numeric" value="<?= e($set['rest_time']) ?>" required/>
                        </label>
                        <div class="flex justify-center">
                            <input class="saveButton w-30 h-30" type="image" alt="speichern" src="/img/save.svg">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
