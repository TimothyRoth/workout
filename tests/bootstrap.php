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

// One migrated database per test run; every database test starts from a copy of it.
$projectDir = dirname(__DIR__);
foreach (glob($projectDir.'/var/workout_test.*db*') ?: [] as $file) {
    unlink($file);
}
exec(sprintf('APP_ENV=test %s %s/bin/console doctrine:migrations:migrate --no-interaction --quiet 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($projectDir)), $output, $exitCode);
if (0 !== $exitCode) {
    fwrite(STDERR, "Could not build the test database:\n".implode("\n", $output)."\n");
    exit(1);
}
rename($projectDir.'/var/workout_test.db', $projectDir.'/var/workout_test.template.db');
foreach (glob($projectDir.'/var/workout_test.db-*') ?: [] as $file) {
    unlink($file);
}
