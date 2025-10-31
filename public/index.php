<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Application;

try {
    Application::run();
} catch (Exception $e) {
    syslog(LOG_ERR, $e->getMessage());
}