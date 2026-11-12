<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repository\UserRepository;
use App\Tests\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCommandsTest extends DatabaseTestCase
{
    public function testCreateListChangeAndDelete(): void
    {
        self::assertSame("User 'smoke' created successfully.", $this->console('app:user:create', ['username' => ' smoke ', 'password' => 'geheim123']));
        self::assertSame("User 'SMOKE' already exists.", $this->console('app:user:create', ['username' => 'SMOKE', 'password' => 'geheim123'], 1));
        self::assertSame('Password must be at least 8 characters.', $this->console('app:user:create', ['username' => 'kurz', 'password' => 'abc'], 1));
        self::assertMatchesRegularExpression('/^\d+\s+smoke\s+\d{4}-/', $this->console('app:user:list'));

        $before = $this->service(UserRepository::class)->findByUsername('smoke')['password_hash'];
        self::assertTrue(password_verify('geheim123', $before));
        self::assertSame("Password of 'smoke' changed; remembered logins were revoked.", $this->console('app:user:password', ['username' => 'Smoke', 'password' => 'anderes-geheim']));
        self::assertTrue(password_verify('anderes-geheim', $this->service(UserRepository::class)->findByUsername('smoke')['password_hash']));

        self::assertSame("User 'nobody' not found.", $this->console('app:user:delete', ['username' => 'nobody'], 1));
        self::assertSame("User 'smoke' deleted.", $this->console('app:user:delete', ['username' => 'smoke']));
        self::assertSame('No users yet. Create one with app:user:create.', $this->console('app:user:list'));
    }

    private function console(string $command, array $arguments = [], int $exitCode = 0): string
    {
        $tester = new CommandTester((new Application(self::$kernel))->find($command));
        self::assertSame($exitCode, $tester->execute($arguments));

        return trim($tester->getDisplay());
    }
}
