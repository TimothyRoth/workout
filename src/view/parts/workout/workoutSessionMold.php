<div class="workoutSessionView">
    <div class="wrapper">
        <div id="metaData">
            <h2 id="workoutName"></h2>
            <p>Workout begonnen um: <span id="startTime"></span></p>
        </div>
        <div class="progress">
            <h3><b><span id="currentExercise"></span>(<span id="exerciseIndex"></span>/<span id="totalExercises"></span>)</b></h3>
            <div class="currentSet">
                <p><b>Satz <span id="current"></span>/<span id="total"></span></b></p>
                <div class="meta">
                    <label class="flex gap-s align-center" for="repInput"><b>Wiederholungen</b><input name="reps" id="repInput" type="text" /></label>
                    <label class="flex gap-s align-center" for="measureUnitInput"><b>Einheit</b><input name="measureUnit" id="measureUnitInput" type="text" /></label>
                </div>
            </div>
            <div class="button mt-2" id="next">Weiter</div>
        </div>
        <div class="summary">
            <p><b>Dauer:</b> <span id="duration"></span></p>
            <p><b>Workload:</b> <span id="workload"></span></p>
            <div class="trigger-accordion">
                <h3 class="trigger">Show Log</h3>
                <div class="trigger-container" id="workout_summary"></div>
            </div>
            <div class="button finishButton mt-2">Speichern und beenden</div>
        </div>
    </div>
</div>