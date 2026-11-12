<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;

final class SecurityTest extends AppWebTestCase
{
    public function testTheLoginPageIsPublic(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('name="password"', $this->body());
        self::assertStringContainsString('<title>Anmelden · Workout</title>', $this->body());
        self::assertStringNotContainsString('install-card', $this->body());
    }

    public function testAWrongPasswordShowsTheGermanErrorOnceAndKeepsTheUsername(): void
    {
        $this->createUser();

        $this->client->request('POST', '/login', ['username' => 'smoke', 'password' => 'falsch']);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/login');
        self::assertStringContainsString('Fehlerhafte Zugangsdaten.', $this->body());
        self::assertStringContainsString('value="smoke"', $this->body());

        $this->client->request('GET', '/login');
        self::assertStringNotContainsString('Fehlerhafte Zugangsdaten.', $this->body());
    }

    public function testLoginIgnoresTheCaseOfTheUsernameAndCanBeRemembered(): void
    {
        $this->createUser();

        $this->client->request('POST', '/login', ['username' => 'SMOKE', 'password' => 'geheim123', 'remember' => '1']);

        self::assertResponseRedirects('/');
        self::assertResponseHasCookie('workout_remember');
        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/', 303);
    }

    public function testWithoutTheCheckboxNothingIsRemembered(): void
    {
        $this->createUser();

        $this->client->request('POST', '/login', ['username' => 'smoke', 'password' => 'geheim123']);

        $remember = array_filter($this->client->getResponse()->headers->getCookies(), static fn ($cookie) => 'workout_remember' === $cookie->getName() && !$cookie->isCleared());
        self::assertSame([], $remember);
    }

    public function testAnOldBcryptHashStillWorksAndIsUpgraded(): void
    {
        static::getContainer()->get(UserRepository::class)->create('smoke', password_hash('geheim123', \PASSWORD_BCRYPT, ['cost' => 10]));

        $this->client->request('POST', '/login', ['username' => 'smoke', 'password' => 'geheim123']);

        self::assertResponseRedirects('/');
        self::assertStringStartsNotWith('$2y$10$', static::getContainer()->get(UserRepository::class)->findByUsername('smoke')['password_hash']);
    }

    public function testLogoutIsPostOnly(): void
    {
        $this->logIn();

        $this->client->request('GET', '/logout');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/logout');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
    }

    public function testPostsFromAnotherOriginOrWithoutOneAreRejected(): void
    {
        $this->createUser();

        $this->client->request('POST', '/login', ['username' => 'smoke', 'password' => 'geheim123'], server: ['HTTP_ORIGIN' => 'https://attacker.example']);
        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Anfrage von fremder Herkunft abgelehnt.', $this->body());

        $this->client->setServerParameter('HTTP_ORIGIN', '');
        $this->client->request('POST', '/login', ['username' => 'smoke', 'password' => 'geheim123']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testErrorsAreGermanPagesOrJson(): void
    {
        $this->logIn();

        $this->client->request('GET', '/nope');
        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('<p>Diese Seite gibt es nicht.</p>', $this->body());

        $this->client->request('GET', '/nope', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(['status' => 'error', 'message' => 'Diese Seite gibt es nicht.'], json_decode($this->body(), true));
    }

    public function testSecurityHeadersAndNoBrowserCachingOfPages(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'same-origin');
        self::assertResponseHeaderSame('X-Frame-Options', 'SAMEORIGIN');
    }
}
