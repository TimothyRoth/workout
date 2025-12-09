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
            CREATE TABLE IF NOT EXISTS workoutLog (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                workload INTEGER NOT NULL,
                duration INTEGER NOT NULL,
                summary TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS workouts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS exercises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(workout_id) REFERENCES workouts(id) ON DELETE CASCADE
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS sets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exercise_id INTEGER NOT NULL,
                measure_unit TEXT NOT NULL,
                repetitions INTEGER,
                rest_time TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
            );
        ");
    }

    public static function getWorkouts(): array
    {
        $db = self::$connection;
        return $db->query("SELECT name, id FROM workouts")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getExercises(int $workoutId): array
    {
        $db = self::$connection;
        $stmt = $db->prepare("SELECT id, name FROM exercises WHERE workout_id = :workout_id");
        $stmt->bindValue(":workout_id", $workoutId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addExercise(string $name, int $workoutId): int
    {
        $db = self::$connection;

        $stmt = $db->prepare("INSERT INTO exercises(name, workout_id) VALUES(:name, :workout_id)");
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':workout_id', $workoutId);
        $stmt->execute();

        return $db->lastInsertId();
    }

    public static function editExercise(int $exerciseId, string $name): int {
        $db = self::$connection;

        $stmt = $db->prepare("UPDATE exercises SET name = :name WHERE id = :id");
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':id', $exerciseId);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public static function getSets(int $exerciseId): array
    {
        $db = self::$connection;
        $stmt = $db->prepare("SELECT * FROM sets WHERE exercise_id = :exercise_id");
        $stmt->bindValue(':exercise_id', $exerciseId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addSet(int $exerciseId, int $repetitions, string $measureUnit, string $restTime): int
    {
        $db = self::$connection;

        $stmt = $db->prepare("INSERT INTO sets(exercise_id, repetitions, measure_unit, rest_time) VALUES(:exercise_id, :repetitions, :measure_unit, :rest_time)");
        $stmt->bindValue(':exercise_id', $exerciseId);
        $stmt->bindValue(':repetitions', $repetitions);
        $stmt->bindValue(':measure_unit', $measureUnit);
        $stmt->bindValue(':rest_time', $restTime);
        $stmt->execute();

        return $db->lastInsertId();
    }

    public static function editSet(int $setId, int $repetitions, string $measureUnit, string $restTime): int
    {
        $db = self::$connection;

        $stmt = $db->prepare("
        UPDATE sets
        SET rest_time = :rest_time,
            repetitions = :repetitions,
            measure_unit = :measure_unit
        WHERE id = :set_id
    ");

        $stmt->bindValue(':repetitions', $repetitions);
        $stmt->bindValue(':measure_unit', $measureUnit);
        $stmt->bindValue(':rest_time', $restTime);
        $stmt->bindValue(':set_id', $setId);

        $stmt->execute();

        return $stmt->rowCount();
    }


    public static function deleteSet(int $id): int
    {
        $db = self::$connection;

        $stmt = $db->prepare("DELETE FROM sets WHERE id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public static function deleteExercise(int $id): int
    {
        $db = self::$connection;

        $stmt = $db->prepare("DELETE FROM exercises WHERE id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return $stmt->rowCount();
    }


    public static function getWorkout(int $id): array
    {
        $db = self::$connection;
        $stmt = $db->prepare("SELECT name, id FROM workouts WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteWorkout(int $id): int
    {
        $db = self::$connection;

        $stmt = $db->prepare("DELETE FROM workouts WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public static function addWorkout(string $name): int
    {
        $db = self::$connection;

        $stmt = $db->prepare("INSERT INTO workouts(name) VALUES(:name)");
        $stmt->bindValue(':name', $name);
        $stmt->execute();

        return (int)$db->lastInsertId();
    }

    public static function editWorkout(int $id, string $name): int {
        $db = self::$connection;

        $stmt = $db->prepare("UPDATE workouts SET name = :name WHERE id = :id");
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function addLog(int $workoutId, int $workload, int $duration, string $summary): int
    {
        $db = self::$connection;

        $stmt = $db->prepare("INSERT INTO workoutLog(workout_id, workload, duration, summary) VALUES(:workout_id, :workload, :duration, :summary)");
        $stmt->bindValue(':workout_id', $workoutId);
        $stmt->bindValue(':workload', $workload);
        $stmt->bindValue(':duration', $duration);
        $stmt->bindValue(':summary', $summary);
        $stmt->execute();

        return (int)$db->lastInsertId();
    }

    public static function getLogs(): array
    {
        $db = self::$connection;

        $query = "
        SELECT
            wl.id,
            wl.workout_id,
            wl.workload AS actual_workload,
            wl.duration,
            wl.created_at,
            wl.summary AS workout_summary,
            w.name AS workout_name,
            MAX(wl.workload) OVER (PARTITION BY wl.workout_id) AS max_workload,
            LAG(wl.workload) OVER (PARTITION BY wl.workout_id ORDER BY wl.created_at) AS previous_workload
        FROM workoutLog wl
        JOIN workouts w ON wl.workout_id = w.id
        ORDER BY wl.created_at DESC;
    ";

        $stmt = $db->query($query);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }


}
