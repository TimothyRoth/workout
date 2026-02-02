let workout = {};
let buttonDisabled = false;

document.addEventListener("DOMContentLoaded", () => {
    editContainer();
    deleteAction();
    initAccordion();
    initWorkoutSession();
});

const editContainer = () => {
    const buttons = document.querySelectorAll(".editButton");
    const body = document.querySelector("body");
    buttons.forEach(button => {
        button.addEventListener("click", () => {
            const target = button.dataset.target;
            const container = document.getElementById(target);

            if (container) {
                container.classList.add("active");
                body.classList.add("no-scroll");

                container.querySelector(".close").addEventListener("click", () => {
                    container.classList.remove("active");
                    body.classList.remove("no-scroll");
                }, {once: true})

                const saveButton = container.querySelector(".saveButton");

                if (saveButton && !saveButton.classList.contains("active")) {
                    const inputFields = container.querySelectorAll("input");
                    inputFields.forEach(input => {

                        const previousValue = input.value;

                        input.addEventListener("change", () => {

                            if (input.value !== previousValue) {
                                saveButton.classList.add("active");
                            } else {
                                saveButton.classList.remove("active");
                            }

                        })
                    })
                }
            }
        })
    })
}

const deleteAction = () => {
    const buttons = document.querySelectorAll(".deleteButton");

    buttons.forEach(button => {
        button.addEventListener("click", (event) => {
            event.preventDefault(); // stop the form from submitting immediately

            // Simple browser confirmation dialog
            const confirmed = confirm("Das Löschen ist unwiderruflich. Bist du sicher?");

            if (confirmed) {
                // If the button is inside a form, submit it programmatically
                const form = button.closest("form");
                if (form) {
                    form.submit();
                }
            }
        });
    });
};

let wakeLock = null;

const requestWakeLock = async () => {
    if (!('wakeLock' in navigator)) return
    if (wakeLock !== null) return;

    try {
        wakeLock = await navigator.wakeLock.request('screen')
    } catch {
        console.info('No wakelock available on this browser.')
    }
}

const initWorkoutSession = () => {
    const button = document.querySelector(".startWorkout");
    const body = document.querySelector("body");

    if (button) {
        button.addEventListener("click", async () => {
            body.classList.add("no-scroll");
            initWorkout();
            initView()
            startProgress();
            await requestWakeLock();

            document.addEventListener('visibilitychange', async () => {
                if (document.visibilityState === 'visible') {
                    await requestWakeLock();
                }
            })
        });
    }
};

const cacheCurrentWorkoutData = () => {
    localStorage.setItem(
        "activeWorkout", JSON.stringify(workout)
    )
}

const clearCache = () => {
    localStorage.clear();
}

const loadCache = () => {
    const strWorkout = localStorage.getItem("activeWorkout");
    return JSON.parse(strWorkout) ?? null;
}

const initWorkout = () => {
    workout.id = document.querySelector("input[name='workout_id']").value;

    //here we have to play with the cached workout.
    const activeWorkout = loadCache();

    if (activeWorkout !== null && activeWorkout.id === workout.id) {
        const confirmContinueWorkout = confirm("Möchtest du das Workout fortsetzen?")

        if (confirmContinueWorkout) {
            workout = activeWorkout;
            return;
        }
    }

    parseWorkoutMetaFromDom();

};

const parseWorkoutMetaFromDom = () => {
    workout.name = document.querySelector("h3").innerText;
    workout.startTime = new Date().getTime();
    workout.exerciseIndex = 0;
    workout.setIndex = 0;

    const exercises = document.querySelectorAll(".exercise");
    workout.exercises = [];

    exercises.forEach((exercise, index) => {
        workout.exercises.push({
            name: exercise.querySelector(".exerciseName").innerText,
            sets: []
        });

        const sets = exercise.querySelectorAll("table tbody tr");
        sets.forEach((set) => {
            workout.exercises[index].sets.push({
                reps: set.querySelectorAll("td")[0].innerText,
                measureUnit: set.querySelectorAll("td")[1].innerText,
                breaktime: set.querySelectorAll("td")[2].innerText,
            });
        })
    });
}
const initView = () => {
    workout.view = document.querySelector(".workoutSessionView");
    workout.view.classList.add("active");
    workout.view.querySelector("#workoutName").innerText = workout.name;
    workout.view.querySelector("#startTime").innerText = new Date(workout.startTime).toLocaleString("de-DE");
}
const startProgress = () => {
    proceed();
    next();
}

const proceed = () => {
    workout.view.querySelector("#currentExercise").innerText = workout.exercises[workout.exerciseIndex].name;
    workout.view.querySelector("#current").innerText = workout.setIndex + 1;
    workout.view.querySelector("#exerciseIndex").innerText = workout.exerciseIndex + 1;
    workout.view.querySelector("#totalExercises").innerText = workout.exercises.length;
    workout.view.querySelector("#total").innerText = workout.exercises[workout.exerciseIndex].sets.length;
    workout.view.querySelector("#repInput").value = workout.exercises[workout.exerciseIndex].sets[workout.setIndex].reps;
    workout.view.querySelector("#measureUnitInput").value = workout.exercises[workout.exerciseIndex].sets[workout.setIndex].measureUnit;
}

const next = () => {
    const button = workout.view.querySelector("#next");
    let finishWorkout = false;

    button.addEventListener("click", async () => {
        if (buttonDisabled) return;

        if (finishWorkout) {
            showSummary(workout.view);
            return;
        }

        const reps = document.querySelector("#repInput").value;
        const measureUnit = document.querySelector("#measureUnitInput").value;

        addToWorkload(reps, measureUnit);

        addToSummary(
            workout.exercises[workout.exerciseIndex].name,
            reps, measureUnit,
            parseInt(workout.exercises[workout.exerciseIndex].sets[workout.setIndex].breaktime
            ));

        const lastExercise = workout.exerciseIndex === workout.exercises.length - 1;
        const lastSet = workout.setIndex === workout.exercises[workout.exerciseIndex].sets.length - 1;

        if (lastExercise && lastSet) {
            button.innerText = "Zusammenfassung anzeigen";
            finishWorkout = true;
            return;
        }

        workout.setIndex++;

        if (workout.setIndex >= workout.exercises[workout.exerciseIndex].sets.length) {
            workout.setIndex = 0;
            workout.exerciseIndex++;
        }

        proceed();
        cacheCurrentWorkoutData();

        const breakTime = parseInt(workout.exercises[workout.exerciseIndex].sets[workout.setIndex].breaktime)
        await initBreak(breakTime);

    });
}

const addToWorkload = (reps, measureUnit) => {

    if (!workout.workload) {
        workout.workload = 0;
    }

    const repsNum = parseInt(reps, 10) || 0;
    const unitNum = parseFloat(measureUnit) || 0;
    workout.workload += repsNum * unitNum;
};

const addToSummary = (exercise, reps, measureUnit, breakTime) => {

    if (!workout.summary) {
        workout.summary = {};
        workout.summary.log = "";
    }

    workout.summary.log += `exercise=${exercise};reps=${reps};measureUnit=${measureUnit};breaktime=${breakTime} \n`;
}

const showSummary = (view) => {
    const summary = view.querySelector(".summary");

    view.querySelector(".progress").classList.add("hide");
    view.querySelector(".summary").classList.add("active");

    const endTime = new Date().getTime();
    workout.duration = Math.floor((endTime - workout.startTime) / 60000);

    summary.querySelector("#duration").innerText = workout.duration + " Minuten";
    summary.querySelector("#workload").innerText = workout.workload;

    parseSummary();

    summary.querySelector("#workout_summary").innerHTML = workout.summary.view;

    const finishWorkoutButton = summary.querySelector(".finishButton");
    finishWorkoutButton.addEventListener("click", finishWorkout)
};

const initBreak = (seconds) => {
    return new Promise((resolve) => {
        const button = workout.view.querySelector("#next");
        const currentButtonText = button.innerText;

        buttonDisabled = true;
        button.classList.add("breaktime");
        workout.view.classList.add("breaktime");

        const start = Date.now();
        const end = start + seconds * 1000;

        const update = () => {
            const now = Date.now();
            const remaining = Math.max(0, Math.ceil((end - now) / 1000));

            if (remaining <= 0) {
                // end reached
                button.innerText = currentButtonText;
                button.classList.remove("breaktime");
                buttonDisabled = false;
                workout.view.classList.remove("breaktime");
                resolve();
                return;
            }

            button.innerText = "Pause: " + remaining + " Sekunden";
            requestAnimationFrame(update); // smoother + not affected by throttling when active
        };

        update();
    });
};


const finishWorkout = async () => {
    const response = await fetch("/api/workout/log", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            workout_id: workout.id,
            duration: workout.duration,
            workload: workout.workload,
            summary: workout.summary.json
        }),
    });

    if (response.ok) {
        clearCache();
        window.location.href = "/logs";
    } else {
        console.error(`Error: ${response.status} - ${response.statusText}`);
        alert("Fehler beim Speichern des Workouts. Bitte versuche es erneut.");
    }
};

const parseSummary = () => {

    const array = workout.summary.log.split("\n");
    const filtered = array.filter(line => line.trim() !== "");

    let exercises = [];
    let jsonResponse = [];
    let jsonIndex = 0;

    workout.summary.view = "";
    workout.summary.json = {};

    filtered.forEach((line) => {
        const splitLine = line.split(";");
        const exercise = splitLine[0].split("=")[1];
        const reps = splitLine[1].split("=")[1];
        const measureUnit = splitLine[2].split("=")[1];
        const breaktime = splitLine[3].split("=")[1];

        if (!exercises.includes(exercise)) {

            if (exercises.length > 0) workout.summary.view += "</div>";
            if (jsonResponse.length > 0) jsonIndex++;

            workout.summary.view += "<div>";
            workout.summary.view += `<h5>Übung: ${exercise}</h5>`;
            exercises.push(exercise);

            jsonResponse.push({
                name: exercise,
                sets: []
            })
        }

        jsonResponse[jsonIndex].sets.push(
            {
                reps,
                measureUnit,
                breaktime
            }
        );

        workout.summary.view += "<div>"
        workout.summary.view += `<p><b>Wiederholungen: </b>${reps}</p>`;
        workout.summary.view += `<p><b>Einheit: </b>${measureUnit}</p>`;
        workout.summary.view += `<p><b>Pause: </b>${breaktime}</p>`;
        workout.summary.view += "</div>"
    })

    if (exercises.length > 0) workout.summary.view += "</div>";
    workout.summary.json = JSON.stringify({exercises: jsonResponse});
}

const initAccordion = () => {
    const accordions = document.querySelectorAll(".trigger-accordion");
    accordions.forEach(accordion => {
        const trigger = accordion.querySelector(".trigger");
        const container = accordion.querySelector(".trigger-container");

        trigger.addEventListener("click", () => {
            if (container.classList.contains("active")) {
                container.classList.remove("active");
                return;
            }

            container.classList.add("active");
        })
    });
}
