<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repository\UserRepository;
use App\Tests\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    public function testUsernamesMatchRegardlessOfCase(): void
    {
        $users = $this->service(UserRepository::class);
        $id = $users->create('Timo', 'hash');

        self::assertSame($id, $users->findByUsername('TIMO')['id']);
        self::assertSame(['Timo'], array_column($users->findAll(), 'username'));

        $users->updatePassword($id, 'new-hash');
        self::assertSame('new-hash', $users->findByUsername('timo')['password_hash']);

        $users->delete($id);
        self::assertNull($users->findByUsername('timo'));
    }
}
