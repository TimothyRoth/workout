<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\DBAL\Connection;

trait FreshTestDatabase
{
    private static function copyTemplateDatabase(): void
    {
        $var = dirname(__DIR__).'/var';
        foreach (glob($var.'/workout_test.db*') ?: [] as $file) {
            unlink($file);
        }
        copy($var.'/workout_test.template.db', $var.'/workout_test.db');
    }

    /** Refuses to go on unless Doctrine points at the test database. */
    private static function isolatedConnection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        $path = (string) ($connection->getParams()['path'] ?? '');
        if (!str_ends_with($path, '/var/workout_test.db')) {
            throw new \LogicException(sprintf('Refusing to run tests: Doctrine is not using the test database (got "%s").', $path));
        }

        return $connection;
    }

    private static function closeConnection(): void
    {
        if (static::$booted) {
            static::getContainer()->get(Connection::class)->close();
        }
    }
}
