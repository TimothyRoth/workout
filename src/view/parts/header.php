<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-visual">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0b1120">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Workout">
    <title><?= e($title !== '' ? $title . ' · Workout' : 'Workout') ?></title>
    <link rel="manifest" href="<?= asset('/manifest.webmanifest') ?>">
    <link rel="icon" href="<?= asset('/img/icon.svg') ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= asset('/img/icon-180.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
    <script type="importmap"><?= import_map() ?></script>
    <script type="module" src="<?= asset('/js/app.js') ?>"></script>
</head>
<body>
<div class="offline-banner" data-offline-banner hidden role="status">
    <?= icon('offline', 'icon-sm') ?> Offline – du siehst den zuletzt geladenen Stand, Änderungen sind gerade nicht möglich.
</div>
<main class="app">
