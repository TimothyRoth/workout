<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

/** Users log in with a username that is unique regardless of case (COLLATE NOCASE). */
final class UserRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array{id: int, username: string, password_hash: string}|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchAssociative('SELECT id, username, password_hash FROM users WHERE username = :username', ['username' => $username]) ?: null;
    }

    /** @return list<array{id: int, username: string, created_at: string}> */
    public function findAll(): array
    {
        return $this->db->fetchAllAssociative('SELECT id, username, created_at FROM users ORDER BY username');
    }

    public function create(string $username, string $passwordHash): int
    {
        $this->db->executeStatement('INSERT INTO users (username, password_hash) VALUES (:username, :hash)', ['username' => $username, 'hash' => $passwordHash]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->executeStatement('UPDATE users SET password_hash = :hash WHERE id = :id', ['hash' => $passwordHash, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->executeStatement('DELETE FROM users WHERE id = :id', ['id' => $id]);
    }
}
