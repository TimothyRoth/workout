<div class="card login-card">
    <div class="login-brand">
        <img src="<?= asset('/img/icon.svg') ?>" alt="" width="72" height="72">
        <h1>Workout</h1>
        <p>Melde dich an, um loszulegen.</p>
    </div>

    <form class="login-form" method="POST" action="/login">
        <?php if ($params['error'] !== '') { ?>
            <div class="alert" role="alert"><?= icon('alert', 'icon-sm') ?><span><?= e($params['error']) ?></span></div>
        <?php } ?>

        <label class="field">
            <span>Benutzername</span>
            <div class="input-group">
                <?= icon('user', 'icon-sm') ?>
                <input class="input" id="login-username" name="username" type="text" value="<?= e($params['username']) ?>" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus>
            </div>
        </label>

        <label class="field">
            <span>Passwort</span>
            <div class="input-group has-action">
                <?= icon('lock', 'icon-sm') ?>
                <input class="input" id="login-password" name="password" type="password" autocomplete="current-password" required>
                <button class="icon-btn input-group-action" type="button" data-password-toggle="login-password" aria-pressed="false" aria-label="Passwort anzeigen">
                    <span class="icon-show"><?= icon('eye') ?></span>
                    <span class="icon-hide"><?= icon('eye-off') ?></span>
                </button>
            </div>
        </label>

        <label class="switch">
            <input type="checkbox" name="remember" value="1" checked>
            <span class="switch-track"></span>
            Angemeldet bleiben
        </label>

        <button class="btn btn-primary btn-block btn-lg" type="submit">Einloggen</button>
    </form>
</div>
