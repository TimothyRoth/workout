<div class="workoutSessionView">
    <div class="wrapper">
        <div class="close-workout absolute right-20 top-20">
            <a href="/workout?workout_id=<?= $params['workout']['id'] ?>">
                <img src="/img/close.svg">
            </a>
        </div>
        <div class="flex column gap-20" id="metaData">
            <h2 class="m-0 font-weight-500" id="workoutName"></h2>
            <p class="m-0 flex column gap-5">Workout begonnen um: <span class="font-weight-300" id="startTime"></span></p>
        </div>
        <div class="progress flex column gap-20 mt-20">
            <h3 class="m-0">
                <span id="currentExercise"></span>
                <span class="font-weight-300 font-size-16 relative bottom-3">(<span id="exerciseIndex"></span>/<span
                            id="totalExercises"></span>)</span>
            </h3>
            <div class="currentSet flex column gap-20">
                <h3 class="m-0">
                    <span>Satz</span>
                    <span class="font-weight-300 font-size-16 relative bottom-3">(<span id="current"></span>/<span
                                id="total"></span>)</span>
                </h3>
                <div class="meta flex column gap-20">
                    <label class="flex gap-5 column" for="repInput"><span
                                class="font-size-20 font-weight-400">Wiederholungen</span><input
                                class="font-size-18 font-weight-300" name="reps"
                                id="repInput"
                                type="text"/></label>
                    <label class="flex gap-5 column font-weight-400" for="measureUnitInput">Einheit<input
                                class="font-weight-300 font-size-18" name="measureUnit" id="measureUnitInput" type="text"/></label>
                </div>
            </div>
            <div class="button mt-20" id="next">
                <img class="nextButton" alt="next" src="/img/back.svg" />
            </div>
        </div>
        <div class="summary flex column gap-20 mt-20">
            <h3 class="m-0 font-weight-400 font-size-20">Dauer: <span id="duration"></span></h3>
            <h3 class="m-0 font-weight-400 font-size-20">Workload: <span id="workload"></span></h3>
            <div class="trigger-accordion">
                <h3 class="trigger m-0 font-weight-400 font-size-20 pb-20">Show Log</h3>
                <div class="trigger-container" id="workout_summary"></div>
            </div>
            <div class="button finishButton">Speichern und beenden</div>
        </div>
    </div>
</div>