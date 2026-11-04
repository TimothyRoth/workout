/**
 * Training session.
 *
 * The whole plan is on screen: every exercise with its sets, the current
 * set expanded with inputs. Finished sets can be tapped to correct them,
 * pending ones to jump ahead. Progress (including a running rest timer)
 * is kept in localStorage so an interrupted session can be resumed, and
 * the done sets are posted to /api/workout/log at the end.
 *
 * Records: every exercise knows its best logged set per unit (from the
 * plan API); a completed set that beats it is flagged on the spot. The
 * summary can write today's reps and loads back into the plan.
 */

import {keepScreenOn} from "app/wakelock";

const STORAGE_KEY = "activeWorkout.v2";
const MAX_AGE_MS = 8 * 60 * 60 * 1000; // a cached session older than this is stale

const UNIT_SHORT = {kg: "kg", bw: "KG", s: "s", min: "min"};
const LOAD_LABELS = {kg: "Gewicht (kg)", s: "Dauer (s)", min: "Dauer (min)"};
const LOAD_STEPS = {kg: 2.5, s: 5, min: 1};
const REST_ADJUST_SECONDS = 30;
const NOTIFICATION_TAG = "rest-timer";

// Mirrors Unit::recordScore(): estimated 1RM for weights, reps for bodyweight, duration for timed sets.
const recordScore = (unit, reps, load) => {
    const r = parseInt(reps, 10) || 0;
    const l = parseFloat(load) || 0;
    if (unit === "kg") return r > 0 ? l * (1 + r / 30) : 0;
    if (unit === "bw") return r;
    return l + r / 1000;
};

const $ = id => document.getElementById(id);
const esc = value => String(value ?? "").replace(/[&<>"']/g, c => ({"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"}[c]));

let session = null;
let bound = false;
let elapsedTimer = null;
let restTimer = null;
let setTimer = null;

export const initSession = () => {
    const startButton = $("start-workout");
    if (!startButton) return;

    startButton.addEventListener("click", async () => {
        startButton.classList.add("is-busy");
        try {
            requestNotificationPermission();
            await start(startButton.dataset.workoutId, startButton.dataset.workoutUpdated);
        } catch (error) {
            console.error(error);
            alert(error.message || "Das Workout konnte nicht gestartet werden.");
        } finally {
            startButton.classList.remove("is-busy");
        }
    });
};

// --- lifecycle ----------------------------------------------------------

const start = async (workoutId, workoutUpdated) => {
    const cached = loadCached(workoutId, workoutUpdated);

    if (cached && confirm("Möchtest du das unterbrochene Workout fortsetzen?")) {
        session = cached;
    } else {
        clearCached();
        session = await build(workoutId, workoutUpdated);
        save();
    }

    bind();
    $("session-name").textContent = session.name;
    $("session").hidden = false;
    document.body.classList.add("is-locked");
    window.scrollTo(0, 0);

    await keepScreenOn();
    render();
    startElapsed();
    if (session.rest && session.rest.endsAt > Date.now()) {
        startRestTicker();
    } else {
        session.rest = null;
    }
    if (session.timer && session.timer.endsAt > Date.now()) {
        startSetTimerTicker();
    } else {
        session.timer = null;
    }
};

const bind = () => {
    if (bound) return;
    bound = true;

    $("session-close").addEventListener("click", closeOverlay);
    $("session-finish").addEventListener("click", openSummary);
    $("rest-skip").addEventListener("click", endRest);
    $("rest-minus").addEventListener("click", () => adjustRest(-REST_ADJUST_SECONDS));
    $("rest-plus").addEventListener("click", () => adjustRest(REST_ADJUST_SECONDS));
    $("summary-save").addEventListener("click", submit);
    $("summary-notes").addEventListener("input", event => {
        session.notes = event.target.value;
        save();
    });

    // Back in the foreground: a rest that ended meanwhile is handled right away, its notification is cleared.
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState !== "visible") return;
        closeRestNotification();
        if (session?.rest && restTimer) startRestTicker();
        if (session?.timer && setTimer) startSetTimerTicker();
    });
    $("summary-discard").addEventListener("click", discard);

    $("session-list").addEventListener("click", event => {
        if (event.target.closest("#cur-done")) {
            completeCurrent();
            return;
        }
        if (event.target.closest("#set-timer-start")) {
            startSetTimer();
            return;
        }
        if (event.target.closest("#set-timer-stop")) {
            stopSetTimer();
            return;
        }
        const row = event.target.closest(".set-row");
        if (row) setCurrent(Number(row.dataset.e), Number(row.dataset.s));
    });

    $("session-list").addEventListener("input", event => {
        const set = currentSet();
        if (!set) return;
        if (event.target.id === "cur-reps") set.reps = event.target.value.trim();
        if (event.target.id === "cur-load") set.load = event.target.value.trim();
        save();
    });
};

const closeOverlay = () => {
    stopTimers();
    $("session").hidden = true;
    document.body.classList.remove("is-locked");
};

const build = async (workoutId, workoutUpdated) => {
    const response = await fetch(`/api/workout/${encodeURIComponent(workoutId)}`, {
        headers: {"Accept": "application/json"},
    });
    if (!response.ok) {
        throw new Error("Das Workout konnte nicht geladen werden.");
    }
    const {workout, last, records = {}} = await response.json();

    if (!workout.exercises || workout.exercises.length === 0) {
        throw new Error("Bitte füge dem Workout mindestens eine Übung hinzu, bevor du es startest.");
    }
    const withoutSets = workout.exercises.find(exercise => !exercise.sets || exercise.sets.length === 0);
    if (withoutSets) {
        throw new Error(`Die Übung „${withoutSets.name}“ hat noch keinen Satz. Bitte füge jeder Übung mindestens einen Satz hinzu.`);
    }

    const lastSetsFor = exercise => last?.exercises?.[`id:${exercise.id}`]
        ?? last?.exercises?.[`name:${exercise.name.trim().toLowerCase()}`]
        ?? [];

    return {
        id: String(workout.id),
        updatedAt: workoutUpdated || workout.updated_at,
        name: workout.name,
        startTime: Date.now(),
        current: {e: 0, s: 0},
        rest: null,
        timer: null, // running countdown of a timed set: {e, s, endsAt, total}
        notes: "",
        last: last ? {workload: Number(last.workload), createdAt: last.created_at} : null,
        exercises: workout.exercises.map(exercise => {
            const lastSets = lastSetsFor(exercise);
            const nameKey = `name:${exercise.name.trim().toLowerCase()}`;
            const units = [...new Set(exercise.sets.map(set => set.unit))];
            return {
                id: exercise.id,
                name: exercise.name,
                notes: exercise.notes ?? "",
                // best logged set per unit, updated during the session so a second improvement also counts
                records: Object.fromEntries(units.map(unit => [unit, records[`${nameKey}|${unit}`]
                    ? {reps: records[`${nameKey}|${unit}`].repetitions, load: records[`${nameKey}|${unit}`].load, score: Number(records[`${nameKey}|${unit}`].score), fresh: false}
                    : null])),
                sets: exercise.sets.map((set, i) => ({
                    setId: set.id,
                    plannedReps: set.repetitions,
                    plannedLoad: set.load,
                    unit: set.unit,
                    restTime: parseInt(set.rest_time, 10) || 0,
                    reps: null,
                    load: null,
                    done: false,
                    record: false,
                    last: lastSets[i] ? {reps: lastSets[i].repetitions, load: lastSets[i].load, unit: lastSets[i].unit} : null,
                })),
            };
        }),
    };
};

// --- persistence --------------------------------------------------------

const save = () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({...session, savedAt: Date.now()}));
    } catch (error) {
        console.warn("Session could not be cached.", error);
    }
};

const clearCached = () => localStorage.removeItem(STORAGE_KEY);

const loadCached = (workoutId, workoutUpdated) => {
    let cached;
    try {
        cached = JSON.parse(localStorage.getItem(STORAGE_KEY));
    } catch {
        return null;
    }
    if (!cached || typeof cached !== "object" || !Array.isArray(cached.exercises)) return null;

    const sameWorkout = String(cached.id) === String(workoutId);
    const fresh = Date.now() - (cached.savedAt || 0) < MAX_AGE_MS;
    const samePlan = !workoutUpdated || cached.updatedAt === workoutUpdated;

    return sameWorkout && fresh && samePlan ? cached : null;
};

// --- state helpers ------------------------------------------------------

const allSets = () => session.exercises.flatMap((exercise, e) => exercise.sets.map((set, s) => ({set, e, s})));
const currentSet = () => session.current ? session.exercises[session.current.e]?.sets[session.current.s] ?? null : null;
const isCurrent = (e, s) => session.current !== null && session.current.e === e && session.current.s === s;

const nextPending = (fromE, fromS) => {
    const entries = allSets();
    const start = entries.findIndex(({e, s}) => e === fromE && s === fromS);
    const after = entries.slice(start + 1).find(({set}) => !set.done);
    return after ?? entries.find(({set}) => !set.done) ?? null;
};

const setWorkload = set => {
    const reps = parseInt(set.reps, 10) || 0;
    return set.unit === "bw" ? reps : reps * (parseFloat(set.load) || 0);
};

const workload = () => allSets().reduce((sum, {set}) => sum + (set.done ? setWorkload(set) : 0), 0);
const doneCount = () => allSets().filter(({set}) => set.done).length;

// --- actions ------------------------------------------------------------

const setCurrent = (e, s) => {
    if (isCurrent(e, s)) return;
    clearSetTimer();
    session.current = {e, s};
    save();
    render();
};

const completeCurrent = () => {
    const set = currentSet();
    if (!set) return;

    set.reps = $("cur-reps")?.value.trim() ?? set.reps;
    if (set.unit !== "bw") set.load = $("cur-load")?.value.trim() ?? set.load;
    set.done = true;
    clearSetTimer();
    checkRecord(session.exercises[session.current.e], set);

    const next = nextPending(session.current.e, session.current.s);
    session.current = next ? {e: next.e, s: next.s} : null;

    if (next && set.restTime > 0) {
        beginRest(set.restTime);
    }

    save();
    render();

    if (!next) openSummary();
};

const checkRecord = (exercise, set) => {
    const score = recordScore(set.unit, set.reps, set.load);
    const best = exercise.records?.[set.unit] ?? null;
    const beaten = score > 0 && (!best || score > best.score);
    set.record = beaten;
    if (beaten) {
        exercise.records = {...exercise.records, [set.unit]: {reps: set.reps, load: set.load, score, fresh: true}};
        navigator.vibrate?.([80, 60, 80]);
    }
};

const recordCount = () => allSets().filter(({set}) => set.done && set.record).length;

const adjustRest = seconds => {
    if (!session.rest) return;
    session.rest.endsAt += seconds * 1000;
    session.rest.total = Math.max(1000, session.rest.total + seconds * 1000);
    if (session.rest.endsAt <= Date.now()) {
        endRest();
        return;
    }
    save();
    startRestTicker();
};

const beginRest = seconds => {
    session.rest = {endsAt: Date.now() + seconds * 1000, total: seconds * 1000};
    startRestTicker();
};

const endRest = () => {
    session.rest = null;
    clearInterval(restTimer);
    restTimer = null;
    $("rest-bar").hidden = true;
    save();
};

// --- timed sets (seconds / minutes) ---------------------------------------
// The current set's load is the duration; "Timer starten" counts it down in place.

const isTimed = set => set.unit === "s" || set.unit === "min";

const timedSeconds = set => {
    const value = parseFloat(String($("cur-load")?.value ?? set.load ?? set.plannedLoad ?? "").replace(",", ".")) || 0;
    return Math.round(set.unit === "min" ? value * 60 : value);
};

const startSetTimer = () => {
    const set = currentSet();
    if (!set || !isTimed(set)) return;
    const seconds = timedSeconds(set);
    if (seconds <= 0) return;
    set.load = $("cur-load")?.value.trim() ?? set.load;
    session.timer = {e: session.current.e, s: session.current.s, endsAt: Date.now() + seconds * 1000, total: seconds * 1000};
    save();
    render();
    startSetTimerTicker();
};

const stopSetTimer = () => {
    clearSetTimer();
    save();
    render();
};

const clearSetTimer = () => {
    session.timer = null;
    clearInterval(setTimer);
    setTimer = null;
};

const startSetTimerTicker = () => {
    clearInterval(setTimer);
    const tick = () => {
        if (!session.timer) return;
        const remaining = session.timer.endsAt - Date.now();
        if (remaining <= 0) {
            navigator.vibrate?.([300, 150, 300, 150, 300]);
            if (document.visibilityState !== "visible") showNotification("Zeit um", "Der Satz ist geschafft – abschließen und weiter.");
            clearSetTimer();
            save();
            render();
            return;
        }
        const remainingEl = $("set-timer-remaining");
        if (remainingEl) remainingEl.textContent = formatDuration(remaining);
        const fill = $("set-timer-fill");
        if (fill) fill.style.width = `${Math.max(0, 1 - remaining / session.timer.total) * 100}%`;
    };
    tick();
    setTimer = setInterval(tick, 250);
};

const renderSetTimer = set => {
    if (!isTimed(set) || set.done) return "";
    const running = session.timer && isCurrent(session.timer.e, session.timer.s) && session.timer.endsAt > Date.now();
    if (running) {
        return `
            <div class="set-timer">
                <div class="set-timer-fill" id="set-timer-fill"></div>
                <b id="set-timer-remaining">${formatDuration(session.timer.endsAt - Date.now())}</b>
                <button class="btn btn-ghost" type="button" id="set-timer-stop">Stopp</button>
            </div>`;
    }
    return `
        <div class="set-timer">
            <span class="hint">Countdown für diesen Satz</span>
            <button class="btn btn-ghost" type="button" id="set-timer-start">${timerIcon} Timer starten</button>
        </div>`;
};

// --- timers -------------------------------------------------------------

const startElapsed = () => {
    clearInterval(elapsedTimer);
    const tick = () => { $("session-elapsed").textContent = formatDuration(Date.now() - session.startTime); };
    tick();
    elapsedTimer = setInterval(tick, 1000);
};

const startRestTicker = () => {
    clearInterval(restTimer);
    const bar = $("rest-bar");
    bar.hidden = false;

    const tick = () => {
        const remaining = session.rest.endsAt - Date.now();
        if (remaining <= 0) {
            navigator.vibrate?.([300, 150, 300]);
            if (document.visibilityState !== "visible") showNotification("Pause vorbei", "Weiter geht’s mit dem nächsten Satz.");
            endRest();
            return;
        }
        $("rest-remaining").textContent = formatDuration(remaining);
        $("rest-fill").style.width = `${Math.max(0, 1 - remaining / session.rest.total) * 100}%`;
    };

    // setInterval + wall clock keeps counting correctly after the screen was off.
    tick();
    restTimer = setInterval(tick, 250);
};

// --- notifications ------------------------------------------------------
// The rest ticker keeps running (throttled) while the app is in the background; when it
// ends there, a notification brings the user back. Android needs it to go through the
// service worker, desktop browsers accept a plain Notification.

const requestNotificationPermission = () => {
    if (!("Notification" in window) || Notification.permission !== "default") return;
    Notification.requestPermission().catch(() => {});
};

const showNotification = async (title, body) => {
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    const options = {body, tag: NOTIFICATION_TAG, renotify: true, vibrate: [300, 150, 300], icon: "/img/icon-192.png", badge: "/img/icon-192.png"};
    try {
        const registration = await navigator.serviceWorker?.getRegistration();
        if (registration) await registration.showNotification(title, options);
        else new Notification(title, options);
    } catch (error) {
        console.warn("Notification failed.", error);
    }
};

const closeRestNotification = async () => {
    try {
        const registration = await navigator.serviceWorker?.getRegistration();
        (await registration?.getNotifications({tag: NOTIFICATION_TAG}))?.forEach(notification => notification.close());
    } catch {
    }
};

const stopTimers = () => {
    clearInterval(elapsedTimer);
    clearInterval(restTimer);
    clearInterval(setTimer);
    elapsedTimer = restTimer = setTimer = null;
};

// --- rendering ----------------------------------------------------------

const render = () => {
    const total = allSets().length;
    const done = doneCount();
    $("session-progress-text").textContent = `${done}/${total} Sätze`;
    $("session-progress").style.width = `${total ? (done / total) * 100 : 0}%`;

    $("session-list").innerHTML = session.exercises.map((exercise, e) => `
        <section class="session-exercise">
            <header class="session-exercise-header">
                <h2>${esc(exercise.name)}</h2>
                ${exercise.sets.some(set => set.last) ? '<span class="hint">letztes Mal</span>' : ""}
            </header>
            ${exercise.notes ? `<p class="session-exercise-notes">${noteIcon}<span>${esc(exercise.notes)}</span></p>` : ""}
            ${renderRecordLine(exercise)}
            ${exercise.sets.map((set, s) => renderSet(set, e, s)).join("")}
        </section>
    `).join("");

    document.querySelector(".set-row.is-current")?.scrollIntoView({block: "center", behavior: "smooth"});
};

const renderRecordLine = exercise => {
    const lines = Object.entries(exercise.records ?? {})
        .filter(([, record]) => record)
        .map(([unit, record]) => `${record.fresh ? "Neuer Bestwert" : "Bestwert"}: ${esc(record.reps)} × ${esc(shortLoad(unit, record.load))}`);
    return lines.length ? `<p class="session-exercise-record">${lines.join(" · ")}</p>` : "";
};

const renderSet = (set, e, s) => {
    const current = isCurrent(e, s);
    const classes = ["set-row", set.done ? "is-done" : "", current ? "is-current" : ""].filter(Boolean).join(" ");
    const shown = set.done ? `${esc(set.reps || 0)} × ${esc(shortLoad(set.unit, set.load))}` : `${esc(set.plannedReps)} × ${esc(shortLoad(set.unit, set.plannedLoad))}`;
    const last = set.last ? `${esc(set.last.reps)} × ${esc(shortLoad(set.last.unit, set.last.load))}` : "";

    return `
        <div class="${classes}" data-e="${e}" data-s="${s}" role="button" tabindex="0">
            <span class="set-num">${set.done ? checkIcon : s + 1}</span>
            <span class="set-plan">${shown}${set.done && set.record ? ' <span class="badge badge-gold set-pr">Bestwert</span>' : ""}</span>
            <span class="set-last">${last}</span>
        </div>
        ${current ? renderCurrent(set) : ""}
    `;
};

const renderCurrent = set => `
    <div class="set-current">
        <div class="grid-2">
            <label class="field" for="cur-reps">
                <span>Wiederholungen</span>
                ${stepper(`<input class="input input-lg" id="cur-reps" type="number" inputmode="numeric" min="0" data-step="1" value="${esc(set.reps ?? set.plannedReps)}">`)}
            </label>
            ${set.unit === "bw" ? "" : `
            <label class="field" for="cur-load">
                <span>${esc(LOAD_LABELS[set.unit] ?? "Last")}</span>
                ${stepper(`<input class="input input-lg" id="cur-load" type="number" inputmode="decimal" min="0" step="any" data-step="${LOAD_STEPS[set.unit] ?? 1}" value="${esc(set.load ?? set.plannedLoad ?? "")}">`)}
            </label>`}
        </div>
        ${renderSetTimer(set)}
        <button class="btn btn-primary btn-block btn-lg" id="cur-done" type="button">${set.done ? "Übernehmen" : "Satz abschließen"}</button>
    </div>
`;

const stepper = input => `
    <div class="stepper stepper-lg">
        <button class="stepper-btn" type="button" data-step="-1" aria-label="Weniger" tabindex="-1">${minusIcon}</button>
        ${input}
        <button class="stepper-btn" type="button" data-step="1" aria-label="Mehr" tabindex="-1">${plusIcon}</button>
    </div>
`;

const svg = path => `<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${path}</svg>`;
const minusIcon = svg('<path d="M5 12h14"/>');
const plusIcon = svg('<path d="M12 5v14M5 12h14"/>');

const timerIcon = svg('<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/>');
const noteIcon = svg('<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>');
const checkIcon = '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';

const shortLoad = (unit, load) => {
    if (unit === "bw") return UNIT_SHORT.bw;
    const value = load === null || load === undefined || load === "" ? "–" : formatNumber(Number(load));
    return `${value} ${UNIT_SHORT[unit] ?? unit}`;
};

const formatNumber = value => Number.isInteger(value) ? String(value) : String(Math.round(value * 100) / 100).replace(".", ",");

const formatDuration = ms => {
    const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    const mm = h > 0 ? String(m).padStart(2, "0") : String(m);
    return `${h > 0 ? `${h}:` : ""}${mm}:${String(s).padStart(2, "0")}`;
};

// --- finishing ----------------------------------------------------------

const durationMinutes = () => Math.max(1, Math.round((Date.now() - session.startTime) / 60000));

const openSummary = () => {
    const total = allSets().length;
    const done = doneCount();
    const load = workload();

    $("summary-duration").textContent = String(durationMinutes());
    $("summary-workload").textContent = formatNumber(load);

    const badges = [];
    if (session.last && session.last.workload > 0) {
        const delta = (load - session.last.workload) / session.last.workload * 100;
        const sign = delta >= 0 ? "+" : "";
        badges.push(`<span class="badge ${delta >= 0 ? "badge-up" : "badge-down"}">${sign}${formatNumber(Math.round(delta * 10) / 10)}% zum letzten Mal</span>`);
    } else if (!session.last) {
        badges.push('<span class="badge badge-neutral">Erstes Mal</span>');
    }
    const records = recordCount();
    if (records > 0) {
        badges.push(`<span class="badge badge-gold">${records === 1 ? "1 neuer Bestwert" : `${records} neue Bestwerte`}</span>`);
    }
    $("summary-badges").innerHTML = badges.join("");

    // Plan write-back: offered when done sets differ from the plan, pre-checked only
    // when nothing was below plan (a weaker day should not downgrade the plan by default).
    const changed = planChanges();
    $("summary-plan").hidden = changed.length === 0;
    $("summary-plan-text").textContent = changed.length === 1 ? "Plan aktualisieren (1 Satz weicht ab)" : `Plan aktualisieren (${changed.length} Sätze weichen ab)`;
    $("summary-update-plan").checked = changed.length > 0 && changed.every(({set}) => !belowPlan(set));

    const open = total - done;
    $("summary-open").hidden = open === 0;
    $("summary-open").textContent = open === 1 ? "1 Satz ist noch offen und wird nicht gespeichert." : `${open} Sätze sind noch offen und werden nicht gespeichert.`;
    $("summary-save").disabled = done === 0;

    $("summary-notes").value = session.notes ?? "";
    $("session-summary").showModal();
};

const differsFromPlan = set => {
    const reps = parseInt(set.reps, 10) || 0;
    if (reps !== (parseInt(set.plannedReps, 10) || 0)) return true;
    if (set.unit === "bw") return false;
    return (parseFloat(set.load) || 0) !== (parseFloat(set.plannedLoad) || 0);
};

const belowPlan = set => {
    const reps = parseInt(set.reps, 10) || 0;
    if (reps < (parseInt(set.plannedReps, 10) || 0)) return true;
    return set.unit !== "bw" && (parseFloat(set.load) || 0) < (parseFloat(set.plannedLoad) || 0);
};

const planChanges = () => allSets().filter(({set}) => set.done && set.setId && differsFromPlan(set));

const loggedExercises = () => session.exercises
    .map(exercise => ({
        exercise_id: exercise.id,
        name: exercise.name,
        sets: exercise.sets.filter(set => set.done).map(set => ({
            set_id: set.setId ?? null,
            reps: parseInt(set.reps, 10) || 0,
            unit: set.unit,
            load: set.unit === "bw" ? null : (set.load === "" || set.load === null ? null : set.load),
            rest_time: set.restTime,
        })),
    }))
    .filter(exercise => exercise.sets.length > 0);

const submit = async () => {
    const button = $("summary-save");
    button.classList.add("is-busy");

    try {
        const response = await fetch("/api/workout/log", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Accept": "application/json"},
            body: JSON.stringify({
                workout_id: Number(session.id),
                duration: durationMinutes(),
                exercises: loggedExercises(),
                update_plan: !$("summary-plan").hidden && $("summary-update-plan").checked,
                notes: session.notes ?? "",
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || `${response.status} ${response.statusText}`);
        }

        clearCached();
        window.location.href = "/logs";
    } catch (error) {
        console.error(error);
        alert("Fehler beim Speichern des Workouts. Bitte versuche es erneut.\n" + (error.message || ""));
    } finally {
        button.classList.remove("is-busy");
    }
};

const discard = () => {
    if (!confirm("Workout verwerfen? Die erfassten Sätze gehen verloren.")) return;
    clearCached();
    $("session-summary").close();
    window.location.reload();
};
