<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Never run the suite against anything but the test database: phpunit.dist.xml forces APP_ENV=test.
if ('test' !== ($_SERVER['APP_ENV'] ?? null)) {
    fwrite(STDERR, sprintf("Refusing to run tests with APP_ENV=%s.\n", var_export($_SERVER['APP_ENV'] ?? null, true)));
    exit(1);
}

$testDatabaseUrl = 'sqlite:///%kernel.project_dir%/var/workout_test.db';
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $testDatabaseUrl;
putenv('DATABASE_URL='.$testDatabaseUrl);

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
