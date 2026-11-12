<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/** Input rules and hashing shared by the user commands. */
final class UserCredentials
{
    public function __construct(private readonly PasswordHasherFactoryInterface $hashers)
    {
    }

    /** @throws \InvalidArgumentException with the message for the console */
    public static function username(string $username): string
    {
        $username = trim($username);
        if ('' === $username || mb_strlen($username) > 180) {
            throw new \InvalidArgumentException('Username must be 1-180 characters.');
        }

        return $username;
    }

    /** @throws \InvalidArgumentException with the message for the console */
    public function hash(string $password): string
    {
        if (\strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }

        return $this->hashers->getPasswordHasher(User::class)->hash($password);
    }
}
