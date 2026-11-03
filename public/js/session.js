/**
 * Training session.
 *
 * The whole plan is on screen: every exercise with its sets, the current
 * set expanded with inputs. Finished sets can be tapped to correct them,
 * pending ones to jump ahead. Progress (including a running rest timer)
 * is kept in localStorage so an interrupted session can be resumed, and
 * the done sets are posted to /api/workout/log at the end.
 */

import {keepScreenOn} from "app/wakelock";

const STORAGE_KEY = "activeWorkout.v2";
const MAX_AGE_MS = 8 * 60 * 60 * 1000; // a cached session older than this is stale

const UNIT_SHORT = {kg: "kg", bw: "KG", s: "s", min: "min"};
const LOAD_LABELS = {kg: "Gewicht (kg)", s: "Dauer (s)", min: "Dauer (min)"};

const $ = id => document.getElementById(id);
const esc = value => String(value ?? "").replace(/[&<>"']/g, c => ({"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"}[c]));

let session = null;
let bound = false;
let elapsedTimer = null;
let restTimer = null;

export const initSession = () => {
    const startButton = $("start-workout");
    if (!startButton) return;

    startButton.addEventListener("click", async () => {
        startButton.classList.add("is-busy");
        try {
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
};

const bind = () => {
    if (bound) return;
    bound = true;

    $("session-close").addEventListener("click", closeOverlay);
    $("session-finish").addEventListener("click", openSummary);
    $("rest-skip").addEventListener("click", endRest);
    $("summary-save").addEventListener("click", submit);
    $("summary-discard").addEventListener("click", discard);

    $("session-list").addEventListener("click", event => {
        if (event.target.closest("#cur-done")) {
            completeCurrent();
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
    const {workout, last} = await response.json();

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
        last: last ? {workload: Number(last.workload), createdAt: last.created_at} : null,
        exercises: workout.exercises.map(exercise => {
            const lastSets = lastSetsFor(exercise);
            return {
                id: exercise.id,
                name: exercise.name,
                sets: exercise.sets.map((set, i) => ({
                    plannedReps: set.repetitions,
                    plannedLoad: set.load,
                    unit: set.unit,
                    restTime: parseInt(set.rest_time, 10) || 0,
                    reps: null,
                    load: null,
                    done: false,
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

    const next = nextPending(session.current.e, session.current.s);
    session.current = next ? {e: next.e, s: next.s} : null;

    if (next && set.restTime > 0) {
        beginRest(set.restTime);
    }

    save();
    render();

    if (!next) openSummary();
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

const stopTimers = () => {
    clearInterval(elapsedTimer);
    clearInterval(restTimer);
    elapsedTimer = restTimer = null;
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
            ${exercise.sets.map((set, s) => renderSet(set, e, s)).join("")}
        </section>
    `).join("");

    document.querySelector(".set-row.is-current")?.scrollIntoView({block: "center", behavior: "smooth"});
};

const renderSet = (set, e, s) => {
    const current = isCurrent(e, s);
    const classes = ["set-row", set.done ? "is-done" : "", current ? "is-current" : ""].filter(Boolean).join(" ");
    const shown = set.done ? `${esc(set.reps || 0)} × ${esc(shortLoad(set.unit, set.load))}` : `${esc(set.plannedReps)} × ${esc(shortLoad(set.unit, set.plannedLoad))}`;
    const last = set.last ? `${esc(set.last.reps)} × ${esc(shortLoad(set.last.unit, set.last.load))}` : "";

    return `
        <div class="${classes}" data-e="${e}" data-s="${s}" role="button" tabindex="0">
            <span class="set-num">${set.done ? checkIcon : s + 1}</span>
            <span class="set-plan">${shown}</span>
            <span class="set-last">${last}</span>
        </div>
        ${current ? renderCurrent(set) : ""}
    `;
};

const renderCurrent = set => `
    <div class="set-current">
        <div class="grid-2">
            <label class="field">
                <span>Wiederholungen</span>
                <input class="input input-lg" id="cur-reps" type="number" inputmode="numeric" min="0" value="${esc(set.reps ?? set.plannedReps)}">
            </label>
            ${set.unit === "bw" ? "" : `
            <label class="field">
                <span>${esc(LOAD_LABELS[set.unit] ?? "Last")}</span>
                <input class="input input-lg" id="cur-load" type="number" inputmode="decimal" min="0" step="any" value="${esc(set.load ?? set.plannedLoad ?? "")}">
            </label>`}
        </div>
        <button class="btn btn-primary btn-block btn-lg" id="cur-done" type="button">${set.done ? "Übernehmen" : "Satz abschließen"}</button>
    </div>
`;

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
    $("summary-badges").innerHTML = badges.join("");

    const open = total - done;
    $("summary-open").hidden = open === 0;
    $("summary-open").textContent = open === 1 ? "1 Satz ist noch offen und wird nicht gespeichert." : `${open} Sätze sind noch offen und werden nicht gespeichert.`;
    $("summary-save").disabled = done === 0;

    $("session-summary").showModal();
};

const loggedExercises = () => session.exercises
    .map(exercise => ({
        exercise_id: exercise.id,
        name: exercise.name,
        sets: exercise.sets.filter(set => set.done).map(set => ({
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
