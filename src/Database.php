<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection === null) {
            try {
                // Database file relative to project root
                $dbPath = __DIR__ . '/../workout.db';

                self::$connection = new PDO('sqlite:' . $dbPath);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Create tables once (if not already created)
                self::initializeSchema();
            } catch (PDOException $e) {
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }

    private static function initializeSchema(): void
    {
        $db = self::$connection;

        $db->exec("
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS exercises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(session_id) REFERENCES sessions(id) ON DELETE CASCADE
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS sets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exercise_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                measure_unit TEXT NOT NULL,
                repetitions INTEGER,
                rest_time INTEGER,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
            );
        ");
    }

    public static function getSessions(): array {
        $db = self::$connection;
        $stmt = $db->prepare("SELECT name, id FROM sessions");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getSession($id): array {
        $db = self::$connection;
        $stmt = $db->prepare("SELECT name, id FROM sessions WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function deleteSession(int $id): int {
        $db = self::$connection;

        $stmt = $db->prepare("DELETE FROM sessions WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount(); // returns number of rows deleted
    }

    public static function createSession(string $name): int {
        $db = self::$connection;

        $stmt = $db->prepare("INSERT INTO sessions(name) VALUES(:name)");
        $stmt->bindValue(':name', $name);
        $stmt->execute();

        return (int) $db->lastInsertId();
    }
}
