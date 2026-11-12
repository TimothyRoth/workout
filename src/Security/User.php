<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** A row of the users table. Everyone who can log in sees all data; there are no roles beyond that. */
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $username,
        private string $passwordHash,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /**
     * After a login re-hashed the password: the logged-in user must carry the new hash too, or the next
     * request sees a changed password and logs the user out.
     */
    public function setPassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
