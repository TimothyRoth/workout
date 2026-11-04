<div class="session" id="session" hidden>
    <div class="app session-shell">
        <header class="session-header">
            <div class="topbar-text">
                <h1 id="session-name"></h1>
                <p class="hint"><span id="session-elapsed">0:00</span> · <span id="session-progress-text"></span></p>
            </div>
            <button class="icon-btn" type="button" id="session-close" aria-label="Workout schließen (kann fortgesetzt werden)"><?= icon('close') ?></button>
        </header>
        <div class="progress-bar"><div class="progress-bar-fill" id="session-progress"></div></div>

        <div class="session-list" id="session-list"></div>

        <button class="btn btn-ghost btn-block btn-lg" type="button" id="session-finish"><?= icon('check') ?> Workout beenden</button>
    </div>

    <div class="rest-bar" id="rest-bar" hidden>
        <div class="rest-bar-fill" id="rest-fill"></div>
        <div class="rest-bar-content">
            <span class="rest-bar-label"><?= icon('timer', 'icon-sm') ?></span>
            <b id="rest-remaining">0:00</b>
            <button class="btn btn-ghost" type="button" id="rest-minus" aria-label="30 Sekunden weniger Pause">−30</button>
            <button class="btn btn-ghost" type="button" id="rest-plus" aria-label="30 Sekunden mehr Pause">+30</button>
            <button class="btn btn-ghost" type="button" id="rest-skip">Weiter</button>
        </div>
    </div>

    <dialog class="sheet" id="session-summary">
        <div class="sheet-header">
            <h2>Workout beenden</h2>
            <button class="icon-btn" type="button" data-close aria-label="Schließen"><?= icon('close') ?></button>
        </div>
        <div class="sheet-body">
            <div class="stats">
                <div class="stat"><b id="summary-duration"></b><span>Minuten</span></div>
                <div class="stat"><b id="summary-workload"></b><span>Workload</span></div>
            </div>
            <div class="badges" id="summary-badges"></div>
            <p class="hint" id="summary-open" hidden></p>
            <label class="switch summary-plan" id="summary-plan" hidden>
                <input type="checkbox" id="summary-update-plan">
                <span class="switch-track"></span>
                <span class="switch-text">
                    <span id="summary-plan-text">Plan aktualisieren</span>
                    <small class="hint">Die heutigen Wiederholungen und Gewichte werden als neuer Plan gespeichert.</small>
                </span>
            </label>
            <button class="btn btn-primary btn-block btn-lg" type="button" id="summary-save">Speichern</button>
        </div>
        <div class="sheet-footer">
            <button class="btn btn-danger btn-block" type="button" id="summary-discard"><?= icon('trash', 'icon-sm') ?> Workout verwerfen</button>
        </div>
    </dialog>
</div>
