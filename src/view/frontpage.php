<div class="wrapper">
    <h3>Trainingseinheiten</h3>
    <?php if (count($params) > 0) { ?>
        <div class="trainingSessions">
            <?php foreach ($params as $session) { ?>
                <a href="/session?session_id=<?= $session['id'] ?>" class="trainingSession">
                    <p><?= $session['name'] ?></p>
                    <form method="POST" action="/deleteSession">
                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                        <input type="submit" value="X">
                    </form>
                </a>
            <?php } ?>
        </div>
    <?php } else { ?>
        <p>Keine Trainingseinheit gefunden.</p>
    <?php } ?>

    <form class="createSession" method="POST" action="/createSession">
        <label>
            <input name="session_name" type="text" required>
        </label>
        <input type="submit" value="Neue Einheit hinzufügen">
    </form>
</div>