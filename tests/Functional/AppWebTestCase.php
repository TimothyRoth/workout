<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use App\Tests\FreshTestDatabase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use App\Security\User;

/**
 * HTTP tests against a fresh copy of the migrated test database. POSTs carry the Origin a browser sends.
 */
abstract class AppWebTestCase extends WebTestCase
{
    use FreshTestDatabase;

    protected KernelBrowser $client;
    protected Connection $db;

    protected function setUp(): void
    {
        self::copyTemplateDatabase();
        $this->client = static::createClient();
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $this->db = self::isolatedConnection();
    }

    protected function tearDown(): void
    {
        self::closeConnection();
        parent::tearDown();
    }

    protected function createUser(string $username = 'smoke', string $password = 'geheim123'): int
    {
        $hash = static::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(User::class)->hash($password);

        return static::getContainer()->get(UserRepository::class)->create($username, $hash);
    }

    protected function logIn(): void
    {
        $this->createUser();
        $this->client->request('POST', '/login', ['username' => 'smoke', 'password' => 'geheim123']);
    }

    protected function body(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }
}
