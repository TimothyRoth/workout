<?php
/**
 * "Als App installieren" – phones/tablets only (js/pwa.js). Native prompt button where the browser offers one,
 * otherwise platform instructions. Hidden when already installed or dismissed.
 */
?>
<section class="install-card" data-install-card hidden aria-label="Workout als App installieren">
    <span class="install-card-icon"><?= icon('phone') ?></span>
    <div class="install-card-text">
        <strong>Workout als App</strong>
        <p>Auf dem Startbildschirm, ohne Browserleiste, mit eigenem Icon.</p>
        <p class="install-card-hint" data-install-hint="ios" hidden>
            In Safari auf „Teilen“ <?= icon('share', 'icon-sm') ?> tippen, dann „Zum Home-Bildschirm“.
        </p>
        <p class="install-card-hint" data-install-hint="firefox" hidden>
            Im Firefox-Menü <?= icon('more', 'icon-sm') ?> auf „Installieren“ tippen.
        </p>
        <p class="install-card-hint" data-install-hint="android" hidden>
            Im Browser-Menü <?= icon('more', 'icon-sm') ?> auf „App installieren“ oder „Zum Startbildschirm hinzufügen“ tippen.
        </p>
    </div>
    <button class="btn btn-primary btn-sm" type="button" data-install-button hidden><?= icon('download', 'icon-sm') ?> Installieren</button>
    <button class="icon-btn install-card-close" type="button" data-install-dismiss aria-label="Hinweis ausblenden"><?= icon('close') ?></button>
</section>
