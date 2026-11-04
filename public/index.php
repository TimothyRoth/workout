<?php

// Built-in dev server (php -S ... public/index.php): existing files are served as they are,
// everything else – including /sw.js, which only exists as a route – comes through here.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

App\Application::run();
