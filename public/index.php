<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

use App\Application;

try {
    Application::run();
} catch (Exception $e) {
    syslog(LOG_ERR, $e->getMessage());
}