<div class="session" id="session" hidden>
    <div class="app">
        <header class="topbar">
            <div class="topbar-text">
                <h1 id="session-name"></h1>
                <p class="hint">Gestartet um <span id="session-start"></span></p>
            </div>
            <a class="icon-btn" href="/workout?workout_id=<?= e($workout['id']) ?>" aria-label="Workout verlassen"><?= icon('close') ?></a>
        </header>

        <section class="session-stage" id="session-stage">
            <div>
                <p class="eyebrow">Übung <span id="session-exercise-index"></span> von <span id="session-exercise-total"></span></p>
                <h2 class="session-exercise" id="session-exercise"></h2>
            </div>
            <div class="session-set">
                <b>Satz <span id="session-set-index"></span></b>
                <span>von <span id="session-set-total"></span></span>
            </div>
            <div class="grid-2">
                <label class="field">
                    <span>Wiederholungen</span>
                    <input class="input input-lg" id="session-reps" name="reps" type="number" inputmode="numeric" min="0">
                </label>
                <label class="field">
                    <span>Einheit</span>
                    <input class="input input-lg" id="session-unit" name="measureUnit" type="text">
                </label>
            </div>
            <button class="btn btn-primary btn-block btn-lg btn-next" type="button" id="session-next">
                <span><?= icon('check') ?></span><span>Satz abschließen</span>
            </button>
        </section>

        <section class="session-summary" id="session-summary" hidden>
            <h2>Geschafft!</h2>
            <div class="stats">
                <div class="stat"><b id="session-duration"></b><span>Minuten</span></div>
                <div class="stat"><b id="session-workload"></b><span>Workload</span></div>
            </div>
            <details class="disclosure">
                <summary>Protokoll anzeigen <?= icon('chevron', 'icon-sm') ?></summary>
                <div class="disclosure-body" id="session-log"></div>
            </details>
            <button class="btn btn-primary btn-block btn-lg" type="button" id="session-finish">Speichern und beenden</button>
        </section>
    </div>
</div>
