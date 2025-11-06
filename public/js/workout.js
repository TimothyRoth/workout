let workout = {};
let buttonDisabled = false;

document.addEventListener("DOMContentLoaded", () => {
    editContainer();
    deleteAction();
    initWorkoutSession();
});

const editContainer = () => {
    const buttons = document.querySelectorAll(".editButton");
    buttons.forEach(button => {
        button.addEventListener("click", () => {
            const target = button.dataset.target;
            const container = document.getElementById(target);

            if (container) {
                container.classList.add("active");
                container.querySelector(".close").addEventListener("click", () => {
                    container.classList.remove("active");
                })

                const saveButton = container.querySelector(".saveButton");
                if(saveButton && !saveButton.classList.contains("active")) {
                    const inputFields = container.querySelectorAll("input");
                    inputFields.forEach(input => {
                        input.addEventListener("change", () => {
                            saveButton.classList.add("active");
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
const initWorkoutSession = () => {
    const button = document.querySelector(".startWorkout");
    button.addEventListener("click", () => {
        workout.startTime = new Date().getTime();
        loadWorkout();
        startProgress(initView());
    });
};

const loadWorkout = () => {
    workout.id = document.querySelector("input[name='workout_id']").value;
    workout.name = document.querySelector("h3").innerText;
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
};
const initView = () => {
    const view = document.querySelector(".workoutSessionView");
    view.classList.add("active");
    view.querySelector("#workoutName").innerText = workout.name;
    view.querySelector("#startTime").innerText = new Date(workout.startTime).toLocaleString("de-DE");

    return view;
}

const startProgress = (view) => {
    let exerciseIndex = 0;
    let setIndex = 0;

    proceed(view);
    next(view, exerciseIndex, setIndex);
}

const proceed = (view, exerciseIndex = 0, setIndex = 0) => {
    view.querySelector("#currentExercise").innerText = workout.exercises[exerciseIndex].name;
    view.querySelector("#current").innerText = setIndex + 1;
    view.querySelector("#exerciseIndex").innerText = exerciseIndex + 1;
    view.querySelector("#totalExercises").innerText = workout.exercises.length;
    view.querySelector("#total").innerText = workout.exercises[exerciseIndex].sets.length;
    view.querySelector("#repInput").value = workout.exercises[exerciseIndex].sets[setIndex].reps;
    view.querySelector("#measureUnitInput").value = workout.exercises[exerciseIndex].sets[setIndex].measureUnit;
}

const next = (view, exerciseIndex, setIndex) => {
    const button = view.querySelector("#next");
    let finishWorkout = false;
    button.addEventListener("click", async () => {
            if(buttonDisabled) return;

            if(finishWorkout) {
                showSummary(view);
                return;
            }

            addWorkload(document.querySelector("#repInput").value, document.querySelector("#measureUnitInput").value);

            const lastExercise = exerciseIndex === workout.exercises.length - 1;
            const lastSet = setIndex === workout.exercises[exerciseIndex].sets.length - 1;

            if (lastExercise && lastSet) {
                button.innerText = "Zusammenfassung anzeigen";
                finishWorkout = true;
                return;
            }

            const breakTime = parseInt(workout.exercises[exerciseIndex].sets[setIndex].breaktime)
            await initBreak(view, breakTime);
            setIndex++;

            if(setIndex >= workout.exercises[exerciseIndex].sets.length) {
                setIndex = 0;
                exerciseIndex++;
            }

            proceed(view, exerciseIndex, setIndex);
        });
}

const addWorkload = (reps, measureUnit) => {

    if(!workout.workload) {
        workout.workload = 0;
    }

    const addedWorkload = reps * parseFloat(measureUnit);
    workout.workload += addedWorkload;
    console.log(workout.workload);
};

const showSummary = (view) => {
    const summary = view.querySelector(".summary");

    view.querySelector(".progress").classList.add("hide");
    view.querySelector(".summary").classList.add("active");

    const endTime = new Date().getTime();
    workout.duration = Math.floor((endTime - workout.startTime) / 60000);

    summary.querySelector("#duration").innerText = workout.duration + " Minuten";
    summary.querySelector("#workload").innerText = workout.workload;

    const finishWorkoutButton = summary.querySelector(".button");
    finishWorkoutButton.addEventListener("click", finishWorkout)
};

const initBreak = (view, seconds) => {
    return new Promise((resolve) => {
        const button = view.querySelector("#next");
        const currentButtonText = button.innerText;

        buttonDisabled = true;
        button.classList.add("breaktime");
        button.innerText = "Noch: " + seconds + " Sekunden";
        view.classList.add("breaktime");

        const timer = setInterval(() => {
            seconds--;

            if (seconds <= 0) {
                clearInterval(timer);
                button.innerText = currentButtonText;
                button.classList.remove("breaktime");
                buttonDisabled = false;
                view.classList.remove("breaktime");
                resolve();
                return;
            }

            button.innerText = "Pause: " + seconds + " Sekunden";
        }, 1000);
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
        }),
    });

    if(response.ok) {
        window.location.href = "/logs";
    } else {
        alert("Fehler beim Speichern des Workouts. Bitte versuche es erneut.");
    }
};