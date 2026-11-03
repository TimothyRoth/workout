/**
 * Workout tracker front-end.
 *
 * Page helpers (edit modals, delete confirmation, accordions) and the
 * training session. The session loads its plan from the JSON API, keeps
 * its progress in localStorage so an interrupted session can be resumed,
 * and posts a structured summary when finished.
 */

const SESSION_STORAGE_KEY = "activeWorkout";
const SESSION_MAX_AGE_MS = 8 * 60 * 60 * 1000; // a cached session older than this is stale

document.addEventListener("DOMContentLoaded", () => {
    initEditContainers();
    initDeleteConfirmations();
    initAccordions();
    initWorkoutSession();
});

// ---------------------------------------------------------------------
// Page helpers
// ---------------------------------------------------------------------

const initEditContainers = () => {
    const body = document.body;

    document.querySelectorAll(".editButton").forEach(button => {
        button.addEventListener("click", () => {
            const container = document.getElementById(button.dataset.target);
            if (!container) return;

            container.classList.add("active");
            body.classList.add("no-scroll");

            const firstField = container.querySelector("input:not([type=hidden]):not([type=image])");
            if (firstField) firstField.focus();

            container.querySelector(".close")?.addEventListener("click", () => {
                container.classList.remove("active");
                body.classList.remove("no-scroll");
            }, {once: true});
        });
    });

    // Reveal the save button once any field differs from its initial value.
    document.querySelectorAll(".edit-container").forEach(container => {
        const saveButton = container.querySelector(".saveButton");
        if (!saveButton) return;

        const fields = [...container.querySelectorAll("input:not([type=hidden]):not([type=image])")];
        const initial = new Map(fields.map(field => [field, field.value]));
        const update = () => {
            const dirty = fields.some(field => field.value !== initial.get(field));
            saveButton.classList.toggle("active", dirty);
        };
        fields.forEach(field => field.addEventListener("input", update));
    });
};

const initDeleteConfirmations = () => {
    document.querySelectorAll(".deleteButton").forEach(button => {
        button.addEventListener("click", event => {
            event.preventDefault();
            if (confirm("Das Löschen ist unwiderruflich. Bist du sicher?")) {
                button.closest("form")?.submit();
            }
        });
    });
};

const initAccordions = () => {
    document.querySelectorAll(".trigger-accordion").forEach(accordion => {
        const trigger = accordion.querySelector(".trigger");
        const container = accordion.querySelector(".trigger-container");
        if (!trigger || !container) return;

        trigger.addEventListener("click", () => {
            const open = !container.classList.contains("active");
            container.classList.toggle("active", open);
            accordion.classList.toggle("active", open);
        });
    });
};

// ---------------------------------------------------------------------
// Wake lock (keeps the screen on during a session)
// ---------------------------------------------------------------------

let wakeLock = null;

const requestWakeLock = async () => {
    if (!("wakeLock" in navigator) || wakeLock !== null) return;
    try {
        wakeLock = await navigator.wakeLock.request("screen");
        wakeLock.addEventListener("release", () => { wakeLock = null; });
    } catch {
        console.info("Wake lock not available.");
    }
};

// ---------------------------------------------------------------------
// Training session
// ---------------------------------------------------------------------

let session = null;
let view = null;
let breakRunning = false;

const initWorkoutSession = () => {
    const startButton = document.querySelector(".startWorkout");
    if (!startButton) return;

    startButton.addEventListener("click", async () => {
        startButton.style.pointerEvents = "none";
        try {
            await startSession(startButton.dataset.workoutId, startButton.dataset.workoutUpdated);
        } catch (error) {
            console.error(error);
            alert(error.message || "Das Workout konnte nicht gestartet werden.");
        } finally {
            startButton.style.pointerEvents = "";
        }
    });
};

const startSession = async (workoutId, workoutUpdated) => {
    const cached = loadCachedSession(workoutId, workoutUpdated);

    if (cached && confirm("Möchtest du das unterbrochene Workout fortsetzen?")) {
        session = cached;
    } else {
        clearCachedSession();
        session = await buildSession(workoutId, workoutUpdated);
        saveSession();
    }

    document.body.classList.add("no-scroll");
    view = document.querySelector(".workoutSessionView");
    view.classList.add("active");
    view.querySelector("#workoutName").textContent = session.name;
    view.querySelector("#startTime").textContent = new Date(session.startTime).toLocaleString("de-DE");

    view.querySelector("#next").addEventListener("click", onNext);
    view.querySelector(".finishButton").addEventListener("click", submitSession);

    await requestWakeLock();
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") requestWakeLock();
    });

    if (session.completed) {
        showSummary();
    } else {
        renderCurrentSet();
    }
};

const buildSession = async (workoutId, workoutUpdated) => {
    const response = await fetch(`/api/workout/${encodeURIComponent(workoutId)}`, {
        headers: {"Accept": "application/json"},
    });
    if (!response.ok) {
        throw new Error("Das Workout konnte nicht geladen werden.");
    }
    const {workout} = await response.json();

    if (!workout.exercises || workout.exercises.length === 0) {
        throw new Error("Bitte füge dem Workout mindestens eine Übung hinzu bevor du es startest.");
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

const saveSession = () => {
    try {
        localStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify({...session, savedAt: Date.now()}));
    } catch (error) {
        console.warn("Session could not be cached.", error);
    }
};

const clearCachedSession = () => {
    localStorage.removeItem(SESSION_STORAGE_KEY);
};

const loadCachedSession = (workoutId, workoutUpdated) => {
    let cached;
    try {
        cached = JSON.parse(localStorage.getItem(SESSION_STORAGE_KEY));
    } catch {
        return null;
    }
    if (!cached || typeof cached !== "object") return null;

    const sameWorkout = String(cached.id) === String(workoutId);
    const fresh = Date.now() - (cached.savedAt || 0) < SESSION_MAX_AGE_MS;
    const samePlan = !workoutUpdated || cached.updatedAt === workoutUpdated;

    return sameWorkout && fresh && samePlan ? cached : null;
};

// --- progress -----------------------------------------------------------

const currentExercise = () => session.exercises[session.exerciseIndex];
const currentSet = () => currentExercise().sets[session.setIndex];

const renderCurrentSet = () => {
    const exercise = currentExercise();
    const set = currentSet();

    view.querySelector("#currentExercise").textContent = exercise.name;
    view.querySelector("#exerciseIndex").textContent = session.exerciseIndex + 1;
    view.querySelector("#totalExercises").textContent = session.exercises.length;
    view.querySelector("#current").textContent = session.setIndex + 1;
    view.querySelector("#total").textContent = exercise.sets.length;
    view.querySelector("#repInput").value = set.reps ?? set.plannedReps;
    view.querySelector("#measureUnitInput").value = set.measureUnit ?? set.plannedUnit;
};

const onNext = async () => {
    if (breakRunning || session.completed) return;

    const set = currentSet();
    set.reps = view.querySelector("#repInput").value.trim();
    set.measureUnit = view.querySelector("#measureUnitInput").value.trim();
    session.workload += (parseInt(set.reps, 10) || 0) * (parseFloat(set.measureUnit) || 0);

    const lastExercise = session.exerciseIndex === session.exercises.length - 1;
    const lastSet = session.setIndex === currentExercise().sets.length - 1;

    if (lastExercise && lastSet) {
        session.completed = true;
        session.endTime = Date.now();
        saveSession();
        showSummary();
        return;
    }

    session.setIndex++;
    if (session.setIndex >= currentExercise().sets.length) {
        session.setIndex = 0;
        session.exerciseIndex++;
    }

    saveSession();
    renderCurrentSet();
    await runBreak(set.breaktime);
};

const runBreak = seconds => new Promise(resolve => {
    if (seconds <= 0) {
        resolve();
        return;
    }

    const button = view.querySelector("#next");
    const originalHtml = button.innerHTML;
    const end = Date.now() + seconds * 1000;

    breakRunning = true;
    button.classList.add("breaktime");
    view.classList.add("breaktime");

    const finish = () => {
        clearInterval(timer);
        button.innerHTML = originalHtml;
        button.classList.remove("breaktime");
        view.classList.remove("breaktime");
        breakRunning = false;
        navigator.vibrate?.([300, 150, 300]);
        resolve();
    };

    const tick = () => {
        const remaining = Math.max(0, Math.ceil((end - Date.now()) / 1000));
        if (remaining <= 0) {
            finish();
            return;
        }
        button.textContent = `Pause: ${remaining} Sekunden`;
    };

    // setInterval + wall clock keeps counting correctly after the screen was off.
    const timer = setInterval(tick, 250);
    tick();
});

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
    const summary = view.querySelector(".summary");
    view.querySelector(".progress").classList.add("hide");
    summary.classList.add("active");

    session.duration = Math.round(((session.endTime || Date.now()) - session.startTime) / 60000);

    summary.querySelector("#duration").textContent = `${session.duration} Minuten`;
    summary.querySelector("#workload").textContent = formatNumber(session.workload);

    const container = summary.querySelector("#workout_summary");
    container.replaceChildren();

    performedExercises().forEach(exercise => {
        const block = document.createElement("div");
        const heading = document.createElement("h5");
        heading.textContent = exercise.name;
        block.append(heading);

        exercise.sets.forEach(set => {
            const row = document.createElement("div");
            row.append(
                summaryLine("Wiederholungen: ", set.reps),
                summaryLine("Einheit: ", set.measureUnit),
                summaryLine("Pause: ", set.breaktime),
            );
            block.append(row);
        });
        container.append(block);
    });
};

const summaryLine = (label, value) => {
    const p = document.createElement("p");
    const b = document.createElement("b");
    b.textContent = label;
    p.append(b, document.createTextNode(value ?? ""));
    return p;
};

const formatNumber = value => Number.isInteger(value) ? String(value) : value.toFixed(2).replace(".", ",");

const submitSession = async () => {
    const button = view.querySelector(".finishButton");
    button.style.pointerEvents = "none";

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

        clearCachedSession();
        window.location.href = "/logs";
    } catch (error) {
        console.error(error);
        alert("Fehler beim Speichern des Workouts. Bitte versuche es erneut.\n" + (error.message || ""));
    } finally {
        button.style.pointerEvents = "";
    }
};
