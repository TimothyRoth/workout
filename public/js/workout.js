let workout = {};
let buttonDisabled = false;

document.addEventListener("DOMContentLoaded", () => {
    editContainer();
    deleteAction();
    initWorkoutSession();
    initAccordion();
    loadWorkout()
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
    const body = document.querySelector("body");
    if(button) {
        button.addEventListener("click", () => {
            body.classList.add("no-scroll");
            workout.startTime = new Date().getTime();
            loadWorkout();
            startProgress(initView());
        });
    }
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
        console.log(exercise)
        const sets = exercise.querySelectorAll("table tbody tr");
        sets.forEach((set) => {
            workout.exercises[index].sets.push({
                reps: set.querySelectorAll("td")[0].innerText,
                measureUnit: set.querySelectorAll("td")[1].innerText,
                breaktime: set.querySelectorAll("td")[2].innerText,
            });
        })
    });

    console.log(workout.exercises)
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

            const reps = document.querySelector("#repInput").value;
            const measureUnit =  document.querySelector("#measureUnitInput").value;

            addToWorkload(reps, measureUnit);

            addToSummary(
                workout.exercises[exerciseIndex].name,
                reps, measureUnit,
                parseInt(workout.exercises[exerciseIndex].sets[setIndex].breaktime
                ));

            const lastExercise = exerciseIndex === workout.exercises.length - 1;
            const lastSet = setIndex === workout.exercises[exerciseIndex].sets.length - 1;

            if (lastExercise && lastSet) {
                button.innerText = "Zusammenfassung anzeigen";
                finishWorkout = true;
                return;
            }

            setIndex++;

            if(setIndex >= workout.exercises[exerciseIndex].sets.length) {
                setIndex = 0;
                exerciseIndex++;
            }

            proceed(view, exerciseIndex, setIndex);

            const breakTime = parseInt(workout.exercises[exerciseIndex].sets[setIndex].breaktime)
            await initBreak(view, breakTime);

        });
}

const addToWorkload = (reps, measureUnit) => {

    if(!workout.workload) {
        workout.workload = 0;
    }

    const repsNum = parseInt(reps, 10) || 0;
    const unitNum = parseFloat(measureUnit) || 0;
    workout.workload += repsNum * unitNum;
};

const addToSummary = (exercise, reps, measureUnit, breakTime) => {

    if(!workout.summary) {
        workout.summary = "";
    }

    workout.summary += `exercise=${exercise} reps=${reps} measureUnit=${measureUnit} breaktime=${breakTime} \n`;
}

const showSummary = (view) => {
    const summary = view.querySelector(".summary");

    view.querySelector(".progress").classList.add("hide");
    view.querySelector(".summary").classList.add("active");

    const endTime = new Date().getTime();
    workout.duration = Math.floor((endTime - workout.startTime) / 60000);

    summary.querySelector("#duration").innerText = workout.duration + " Minuten";
    summary.querySelector("#workload").innerText = workout.workload;

    workout.summary = parseSummary(workout.summary);
    summary.querySelector("#workout_summary").innerHTML = workout.summary;

    const finishWorkoutButton = summary.querySelector(".finishButton");
    finishWorkoutButton.addEventListener("click", finishWorkout)
};

const initBreak = (view, seconds) => {
    return new Promise((resolve) => {
        const button = view.querySelector("#next");
        const currentButtonText = button.innerText;

        buttonDisabled = true;
        button.classList.add("breaktime");
        view.classList.add("breaktime");

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
                view.classList.remove("breaktime");
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
            summary: workout.summary
        }),
    });

    if(response.ok) {
        window.location.href = "/logs";
    } else {
        console.error(`Error: ${response.status} - ${response.statusText}`);
        alert("Fehler beim Speichern des Workouts. Bitte versuche es erneut.");
    }
};

const parseSummary = summary => {

    const array = summary.split("\n");
    const filtered = array.filter(line => line.trim() !== "");

    let exercises = [];
    let output = "";

    filtered.forEach(line => {
        const splitLine = line.split(" ");
        const exercise = splitLine[0].split("=")[1];
        const reps = splitLine[1].split("=")[1];
        const measureUnit = splitLine[2].split("=")[1];
        const breaktime = splitLine[3].split("=")[1];

        if(!exercises.includes(exercise)) {
            if(exercises.length > 0) output += "</div>";
            output += "<div>";
            output += `<h5>Übung: ${exercise}</h5>`;
            exercises.push(exercise);
        }

        output += "<div>"
        output += `<p><b>Wiederholungen: </b>${reps}</p>`;
        output += `<p><b>Einheit: </b>${measureUnit}</p>`;
        output += `<p><b>Pause: </b>${breaktime}</p>`;
        output += "</div>"
    })

    if (exercises.length > 0) output += "</div>";
    return output;
}

const initAccordion = () => {
    const accordions = document.querySelectorAll(".trigger-accordion");
    accordions.forEach(accordion => {
       const trigger = accordion.querySelector(".trigger");
       const container = accordion.querySelector(".trigger-container");

       trigger.addEventListener("click", () => {
           if(container.classList.contains("active")) {
               container.classList.remove("active");
               return;
           }

           container.classList.add("active");
       })
    });
}
