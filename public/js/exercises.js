/**
 * Collapsible exercises on the workout page (<details class="exercise">).
 *
 * The open state is remembered per workout in localStorage, so the page
 * comes back the same way after a set edit (every edit reloads the page).
 * An #exercise-<id> hash (the redirect target after an edit) opens that
 * exercise regardless.
 */

const storageKey = workoutId => `exercises-open:${workoutId}`;

const readOpen = key => {
    try {
        const stored = JSON.parse(localStorage.getItem(key) ?? "null");
        return Array.isArray(stored) ? stored.map(String) : null;
    } catch {
        return null;
    }
};

const writeOpen = (key, exercises) => {
    try {
        localStorage.setItem(key, JSON.stringify(exercises.filter(e => e.open).map(e => e.dataset.exerciseId)));
    } catch {
    }
};

export const initExercises = () => {
    const exercises = [...document.querySelectorAll("details.exercise")];
    if (exercises.length === 0) return;

    const workoutId = document.getElementById("start-workout")?.dataset.workoutId ?? "";
    const key = storageKey(workoutId);
    const stored = readOpen(key);

    if (stored) {
        exercises.forEach(exercise => exercise.open = stored.includes(exercise.dataset.exerciseId));
    }

    const hashed = /^#exercise-\d+$/.test(location.hash) ? document.querySelector(location.hash) : null;
    if (hashed instanceof HTMLDetailsElement) {
        hashed.open = true;
        hashed.scrollIntoView({block: "start"});
    }

    exercises.forEach(exercise => exercise.addEventListener("toggle", () => writeOpen(key, exercises)));
};
