<?php

// Exits 0 when the login page renders, 1 otherwise. Run by bin/update.sh after every deployment.
// Opens the database like a request would, so it also applies pending schema migrations.

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/login';
$_SERVER['HTTP_HOST'] ??= 'localhost';

ob_start();
App\Application::run();
$html = (string)ob_get_clean();

// On the CLI the status stays unset (false) unless the app sets one, e.g. 500 on an error.
$status = http_response_code() ?: 200;

if ($status !== 200 || !str_contains($html, 'name="password"')) {
    fwrite(STDERR, sprintf("The login page answers with HTTP %d. See the PHP error log for the reason.\n", $status));
    exit(1);
}

echo "The login page loads.\n";
