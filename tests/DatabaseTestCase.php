<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every test gets a fresh copy of the migrated test database (built once by tests/bootstrap.php).
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    use FreshTestDatabase;

    protected Connection $db;

    protected function setUp(): void
    {
        self::copyTemplateDatabase();
        self::bootKernel();
        $this->db = self::isolatedConnection();
    }

    protected function tearDown(): void
    {
        self::closeConnection();
        parent::tearDown();
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function service(string $class): object
    {
        return static::getContainer()->get($class);
    }
}
