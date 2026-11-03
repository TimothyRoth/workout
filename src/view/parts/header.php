<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>Workout</title>
    <?php foreach (['lib', 'main', 'workout', 'editContainer', 'exercise', 'button', 'workoutSession', 'logs', 'accordion'] as $css) { ?>
    <link rel="stylesheet" href="<?= asset("/css/{$css}.css") ?>"/>
    <?php } ?>
    <script src="<?= asset('/js/workout.js') ?>" defer></script>
</head>
<body>
<main class="pb-20">
