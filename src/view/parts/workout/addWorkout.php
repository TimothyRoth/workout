<form class="card inline-form" method="POST" action="/addWorkout">
    <input class="input" name="workout_name" type="text" placeholder="Neues Workout" maxlength="100" autocomplete="off" required>
    <button class="icon-btn icon-btn-accent" type="submit" aria-label="Workout hinzufügen"><?= icon('plus') ?></button>
</form>
