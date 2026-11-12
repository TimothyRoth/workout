<header class="topbar">
    <a class="icon-btn" href="/" aria-label="Zur Startseite"><?= icon('back') ?></a>
    <h1>Fehler <?= e($params['status']) ?></h1>
</header>
<div class="card error-card">
    <p><?= e($params['message']) ?></p>
    <a class="btn btn-ghost btn-block" href="/">Zur Startseite</a>
</div>
