/**
 * Training session.
 *
 * Loads the plan from GET /api/workout/{id}, keeps progress in localStorage
 * so an interrupted session can be resumed, and posts a structured summary
 * to POST /api/workout/log when finished.
 */

import {keepScreenOn} from "app/wakelock";

const STORAGE_KEY = "activeWorkout";
const MAX_AGE_MS = 8 * 60 * 60 * 1000; // a cached session older than this is stale

const $ = id => document.getElementById(id);

let session = null;
let breakRunning = false;

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

const start = async (workoutId, workoutUpdated) => {
    const cached = loadCached(workoutId, workoutUpdated);

    if (cached && confirm("Möchtest du das unterbrochene Workout fortsetzen?")) {
        session = cached;
    } else {
        clearCached();
        session = await build(workoutId, workoutUpdated);
        save();
    }

    $("session-name").textContent = session.name;
    $("session-start").textContent = new Date(session.startTime).toLocaleTimeString("de-DE", {hour: "2-digit", minute: "2-digit"});
    $("session-next").addEventListener("click", onNext);
    $("session-finish").addEventListener("click", submit);

    $("session").hidden = false;
    document.body.classList.add("is-locked");
    window.scrollTo(0, 0);

    await keepScreenOn();

    if (session.completed) {
        showSummary();
    } else {
        renderCurrentSet();
    }
};

const build = async (workoutId, workoutUpdated) => {
    const response = await fetch(`/api/workout/${encodeURIComponent(workoutId)}`, {
        headers: {"Accept": "application/json"},
    });
    if (!response.ok) {
        throw new Error("Das Workout konnte nicht geladen werden.");
    }
    const {workout} = await response.json();

    if (!workout.exercises || workout.exercises.length === 0) {
        throw new Error("Bitte füge dem Workout mindestens eine Übung hinzu, bevor du es startest.");
    }
    const withoutSets = workout.exercises.find(exercise => !exercise.sets || exercise.sets.length === 0);
    if (withoutSets) {
        throw new Error(`Die Übung „${withoutSets.name}“ hat noch keinen Satz. Bitte füge jeder Übung mindestens einen Satz hinzu.`);
    }

    return {
        id: String(workout.id),
        updatedAt: workoutUpdated || workout.updated_at,
        name: workout.name,
        startTime: Date.now(),
        exerciseIndex: 0,
        setIndex: 0,
        workload: 0,
        completed: false,
        exercises: workout.exercises.map(exercise => ({
            id: exercise.id,
            name: exercise.name,
            sets: exercise.sets.map(set => ({
                plannedReps: set.repetitions,
                plannedUnit: set.measure_unit,
                breaktime: parseInt(set.rest_time, 10) || 0,
                reps: null,
                measureUnit: null,
            })),
        })),
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
    if (!cached || typeof cached !== "object") return null;

    const sameWorkout = String(cached.id) === String(workoutId);
    const fresh = Date.now() - (cached.savedAt || 0) < MAX_AGE_MS;
    const samePlan = !workoutUpdated || cached.updatedAt === workoutUpdated;

    return sameWorkout && fresh && samePlan ? cached : null;
};

// --- progress -----------------------------------------------------------

const currentExercise = () => session.exercises[session.exerciseIndex];
const currentSet = () => currentExercise().sets[session.setIndex];

const renderCurrentSet = () => {
    const exercise = currentExercise();
    const set = currentSet();

    $("session-exercise").textContent = exercise.name;
    $("session-exercise-index").textContent = session.exerciseIndex + 1;
    $("session-exercise-total").textContent = session.exercises.length;
    $("session-set-index").textContent = session.setIndex + 1;
    $("session-set-total").textContent = exercise.sets.length;
    $("session-reps").value = set.reps ?? set.plannedReps;
    $("session-unit").value = set.measureUnit ?? set.plannedUnit;
};

const onNext = async () => {
    if (breakRunning || session.completed) return;

    const set = currentSet();
    set.reps = $("session-reps").value.trim();
    set.measureUnit = $("session-unit").value.trim();
    session.workload += (parseInt(set.reps, 10) || 0) * (parseFloat(set.measureUnit) || 0);

    const lastExercise = session.exerciseIndex === session.exercises.length - 1;
    const lastSet = session.setIndex === currentExercise().sets.length - 1;

    if (lastExercise && lastSet) {
        session.completed = true;
        session.endTime = Date.now();
        save();
        showSummary();
        return;
    }

    session.setIndex++;
    if (session.setIndex >= currentExercise().sets.length) {
        session.setIndex = 0;
        session.exerciseIndex++;
    }

    save();
    renderCurrentSet();
    await runBreak(set.breaktime);
};

const runBreak = seconds => new Promise(resolve => {
    if (seconds <= 0) {
        resolve();
        return;
    }

    const button = $("session-next");
    const originalHtml = button.innerHTML;
    const total = seconds * 1000;
    const end = Date.now() + total;

    breakRunning = true;
    button.classList.add("is-break");

    const finish = () => {
        clearInterval(timer);
        button.innerHTML = originalHtml;
        button.classList.remove("is-break");
        button.style.removeProperty("--progress");
        breakRunning = false;
        navigator.vibrate?.([300, 150, 300]);
        resolve();
    };

    const tick = () => {
        const remainingMs = end - Date.now();
        if (remainingMs <= 0) {
            finish();
            return;
        }
        const remaining = Math.ceil(remainingMs / 1000);
        button.style.setProperty("--progress", String(1 - remainingMs / total));
        button.replaceChildren(label(`Pause · ${remaining} s`));
    };

    // setInterval + wall clock keeps counting correctly after the screen was off.
    const timer = setInterval(tick, 250);
    tick();
});

const label = text => {
    const span = document.createElement("span");
    span.textContent = text;
    return span;
};

// --- summary ------------------------------------------------------------

const performedExercises = () => session.exercises.map(exercise => ({
    name: exercise.name,
    sets: exercise.sets
        .filter(set => set.reps !== null)
        .map(set => ({
            reps: set.reps,
            measureUnit: set.measureUnit,
            breaktime: String(set.breaktime),
        })),
}));

const showSummary = () => {
    $("session-stage").hidden = true;
    $("session-summary").hidden = false;

    session.duration = Math.round(((session.endTime || Date.now()) - session.startTime) / 60000);

    $("session-duration").textContent = String(session.duration);
    $("session-workload").textContent = formatNumber(session.workload);

    const container = $("session-log");
    container.replaceChildren(...performedExercises().map(exerciseBlock));
};

const exerciseBlock = exercise => {
    const block = document.createElement("div");
    const heading = document.createElement("h3");
    heading.textContent = exercise.name;

    const table = document.createElement("table");
    table.className = "sets";
    const head = table.createTHead().insertRow();
    ["#", "Wdh.", "Einheit", "Pause"].forEach((text, i) => {
        const th = document.createElement("th");
        th.textContent = text;
        if (i === 0) th.className = "num";
        head.append(th);
    });
    const body = table.createTBody();
    exercise.sets.forEach((set, i) => {
        const row = body.insertRow();
        row.insertCell().textContent = String(i + 1);
        row.cells[0].className = "num";
        row.insertCell().textContent = set.reps ?? "";
        row.insertCell().textContent = set.measureUnit ?? "";
        row.insertCell().textContent = `${set.breaktime} s`;
    });

    block.append(heading, table);
    return block;
};

const formatNumber = value => Number.isInteger(value) ? String(value) : value.toFixed(2).replace(".", ",");

const submit = async () => {
    const button = $("session-finish");
    button.classList.add("is-busy");

    try {
        const response = await fetch("/api/workout/log", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Accept": "application/json"},
            body: JSON.stringify({
                workout_id: Number(session.id),
                duration: session.duration,
                workload: session.workload,
                summary: {exercises: performedExercises()},
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
