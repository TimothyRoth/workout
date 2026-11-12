<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

/**
 * SQLite settings on every connection: foreign keys on (deletes rely on ON DELETE CASCADE),
 * WAL journal (readers never block the writer) and a 5 s busy timeout instead of an
 * immediate "database is locked".
 */
#[AsMiddleware]
final class SqlitePragmaMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[SensitiveParameter] array $params): DriverConnection
            {
                $connection = parent::connect($params);

                if (str_contains($params['driver'] ?? '', 'sqlite')) {
                    $connection->exec('PRAGMA foreign_keys = ON');
                    $connection->exec('PRAGMA busy_timeout = 5000');
                    $connection->exec('PRAGMA journal_mode = WAL');
                }

                return $connection;
            }
        };
    }
}
