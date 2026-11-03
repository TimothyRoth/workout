<?php

namespace App;

use PDO;
use RuntimeException;

class Database
{
    private const SCHEMA_VERSION = 1;

    private static ?PDO $connection = null;

    public static function connect(?string $dbPath = null): PDO
    {
        if (self::$connection === null) {
            $dbPath ??= $_ENV['WORKOUT_DB'] ?? getenv('WORKOUT_DB') ?: __DIR__ . '/../workout.db';

            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // SQLite does not enforce FOREIGN KEY ... ON DELETE CASCADE unless this is on.
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');

            self::$connection = $pdo;
            self::migrate();
        }

        return self::$connection;
    }

    public static function pdo(): PDO
    {
        return self::connect();
    }

    // ---------------------------------------------------------------
    // Schema
    // ---------------------------------------------------------------

    private static function migrate(): void
    {
        $db = self::$connection;
        $version = (int)$db->query('PRAGMA user_version')->fetchColumn();

        if ($version >= self::SCHEMA_VERSION) {
            return;
        }

        $db->beginTransaction();
        try {
            if ($version < 1) {
                self::migrateToV1();
            }
            $db->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * v1: base tables for fresh installs, position columns for ordering,
     * cleanup of rows orphaned while foreign keys were still off.
     */
    private static function migrateToV1(): void
    {
        $db = self::$connection;

        $db->exec("
            CREATE TABLE IF NOT EXISTS workouts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS exercises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(workout_id) REFERENCES workouts(id) ON DELETE CASCADE
            )
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
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS workoutLog (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                workload INTEGER NOT NULL,
                duration INTEGER NOT NULL,
                summary TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Rows left behind by deletes that ran without foreign key enforcement.
        $db->exec("DELETE FROM sets WHERE exercise_id NOT IN (SELECT id FROM exercises)");
        $db->exec("DELETE FROM exercises WHERE workout_id NOT IN (SELECT id FROM workouts)");

        // Names were saved untrimmed; the session compares them by name.
        $db->exec("UPDATE workouts SET name = TRIM(name)");
        $db->exec("UPDATE exercises SET name = TRIM(name)");
        $db->exec("UPDATE sets SET measure_unit = TRIM(measure_unit), rest_time = TRIM(rest_time)");

        // Explicit ordering instead of relying on rowid order.
        if (!self::columnExists('exercises', 'position')) {
            $db->exec("ALTER TABLE exercises ADD COLUMN position INTEGER NOT NULL DEFAULT 0");
            $db->exec("UPDATE exercises SET position = id");
        }
        if (!self::columnExists('sets', 'position')) {
            $db->exec("ALTER TABLE sets ADD COLUMN position INTEGER NOT NULL DEFAULT 0");
            $db->exec("UPDATE sets SET position = id");
        }

        // Logs must survive workout deletion, so remember the name at log time.
        if (!self::columnExists('workoutLog', 'workout_name')) {
            $db->exec("ALTER TABLE workoutLog ADD COLUMN workout_name TEXT");
            $db->exec("UPDATE workoutLog SET workout_name = (SELECT name FROM workouts WHERE workouts.id = workoutLog.workout_id)");
        }

        $db->exec("CREATE INDEX IF NOT EXISTS idx_exercises_workout ON exercises(workout_id, position)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sets_exercise ON sets(exercise_id, position)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_log_workout_created ON workoutLog(workout_id, created_at)");
    }

    private static function columnExists(string $table, string $column): bool
    {
        foreach (self::$connection->query("PRAGMA table_info($table)") as $row) {
            if ($row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------
    // Workouts
    // ---------------------------------------------------------------

    public static function getWorkouts(): array
    {
        return self::pdo()->query("SELECT id, name FROM workouts ORDER BY id")->fetchAll();
    }

    public static function getWorkout(int $id): ?array
    {
        $stmt = self::pdo()->prepare("SELECT id, name, updated_at FROM workouts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Workout with its exercises and sets nested, in display order.
     */
    public static function getWorkoutPlan(int $id): ?array
    {
        $workout = self::getWorkout($id);
        if ($workout === null) {
            return null;
        }

        $workout['exercises'] = self::getExercises($id);
        if ($workout['exercises'] === []) {
            return $workout;
        }

        $ids = array_column($workout['exercises'], 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = self::pdo()->prepare(
            "SELECT id, exercise_id, repetitions, measure_unit, rest_time
             FROM sets WHERE exercise_id IN ($placeholders)
             ORDER BY exercise_id, position, id"
        );
        $stmt->execute($ids);

        $setsByExercise = [];
        foreach ($stmt->fetchAll() as $set) {
            $setsByExercise[$set['exercise_id']][] = $set;
        }

        foreach ($workout['exercises'] as &$exercise) {
            $exercise['sets'] = $setsByExercise[$exercise['id']] ?? [];
        }
        unset($exercise);

        return $workout;
    }

    public static function addWorkout(string $name): int
    {
        $stmt = self::pdo()->prepare("INSERT INTO workouts(name) VALUES(:name)");
        $stmt->execute([':name' => $name]);
        return (int)self::pdo()->lastInsertId();
    }

    public static function editWorkout(int $id, string $name): int
    {
        $stmt = self::pdo()->prepare("UPDATE workouts SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':name' => $name, ':id' => $id]);
        return $stmt->rowCount();
    }

    public static function deleteWorkout(int $id): int
    {
        $stmt = self::pdo()->prepare("DELETE FROM workouts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }

    // ---------------------------------------------------------------
    // Exercises
    // ---------------------------------------------------------------

    public static function getExercises(int $workoutId): array
    {
        $stmt = self::pdo()->prepare("SELECT id, workout_id, name FROM exercises WHERE workout_id = :workout_id ORDER BY position, id");
        $stmt->execute([':workout_id' => $workoutId]);
        return $stmt->fetchAll();
    }

    public static function getExercise(int $id): ?array
    {
        $stmt = self::pdo()->prepare("SELECT id, workout_id, name FROM exercises WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function addExercise(string $name, int $workoutId): int
    {
        $db = self::pdo();
        $stmt = $db->prepare(
            "INSERT INTO exercises(name, workout_id, position)
             VALUES(:name, :workout_id, (SELECT COALESCE(MAX(position), 0) + 1 FROM exercises WHERE workout_id = :workout_id))"
        );
        $stmt->execute([':name' => $name, ':workout_id' => $workoutId]);
        self::touchWorkout($workoutId);
        return (int)$db->lastInsertId();
    }

    public static function editExercise(int $exerciseId, string $name): int
    {
        $stmt = self::pdo()->prepare("UPDATE exercises SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':name' => $name, ':id' => $exerciseId]);
        self::touchWorkoutOfExercise($exerciseId);
        return $stmt->rowCount();
    }

    public static function deleteExercise(int $id): int
    {
        self::touchWorkoutOfExercise($id);
        $stmt = self::pdo()->prepare("DELETE FROM exercises WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }

    // ---------------------------------------------------------------
    // Sets
    // ---------------------------------------------------------------

    public static function getSet(int $id): ?array
    {
        $stmt = self::pdo()->prepare("SELECT id, exercise_id, repetitions, measure_unit, rest_time FROM sets WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function addSets(int $exerciseId, int $amount, int $repetitions, string $measureUnit, string $restTime): void
    {
        $db = self::pdo();
        $stmt = $db->prepare(
            "INSERT INTO sets(exercise_id, repetitions, measure_unit, rest_time, position)
             VALUES(:exercise_id, :repetitions, :measure_unit, :rest_time,
                    (SELECT COALESCE(MAX(position), 0) + 1 FROM sets WHERE exercise_id = :exercise_id))"
        );

        $db->beginTransaction();
        try {
            for ($i = 0; $i < $amount; $i++) {
                $stmt->execute([
                    ':exercise_id' => $exerciseId,
                    ':repetitions' => $repetitions,
                    ':measure_unit' => $measureUnit,
                    ':rest_time' => $restTime,
                ]);
            }
            self::touchWorkoutOfExercise($exerciseId);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function editSet(int $setId, int $repetitions, string $measureUnit, string $restTime): int
    {
        $stmt = self::pdo()->prepare(
            "UPDATE sets
             SET repetitions = :repetitions, measure_unit = :measure_unit, rest_time = :rest_time, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            ':repetitions' => $repetitions,
            ':measure_unit' => $measureUnit,
            ':rest_time' => $restTime,
            ':id' => $setId,
        ]);
        self::touchWorkoutOfSet($setId);
        return $stmt->rowCount();
    }

    public static function deleteSet(int $id): int
    {
        self::touchWorkoutOfSet($id);
        $stmt = self::pdo()->prepare("DELETE FROM sets WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }

    // ---------------------------------------------------------------
    // Logs
    // ---------------------------------------------------------------

    public static function addLog(int $workoutId, float $workload, int $duration, string $summary): int
    {
        $workout = self::getWorkout($workoutId);
        if ($workout === null) {
            throw new RuntimeException("Workout $workoutId does not exist");
        }

        $stmt = self::pdo()->prepare(
            "INSERT INTO workoutLog(workout_id, workout_name, workload, duration, summary)
             VALUES(:workout_id, :workout_name, :workload, :duration, :summary)"
        );
        $stmt->execute([
            ':workout_id' => $workoutId,
            ':workout_name' => $workout['name'],
            ':workload' => $workload,
            ':duration' => $duration,
            ':summary' => $summary,
        ]);
        return (int)self::pdo()->lastInsertId();
    }

    /**
     * All logs, newest first. previous_workload and best_before are computed
     * only over entries that existed at the time of each log, so an old
     * personal record is not retroactively downgraded by later sessions.
     */
    public static function getLogs(): array
    {
        $sql = "
            SELECT
                wl.id,
                wl.workout_id,
                COALESCE(w.name, wl.workout_name, 'Gelöschtes Workout') AS workout_name,
                w.id IS NULL AS workout_deleted,
                wl.workload AS actual_workload,
                wl.duration,
                wl.created_at,
                wl.summary AS workout_summary,
                MAX(wl.workload) OVER (
                    PARTITION BY wl.workout_id ORDER BY wl.created_at, wl.id
                    ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                ) AS best_before,
                LAG(wl.workload) OVER (
                    PARTITION BY wl.workout_id ORDER BY wl.created_at, wl.id
                ) AS previous_workload
            FROM workoutLog wl
            LEFT JOIN workouts w ON wl.workout_id = w.id
            ORDER BY wl.created_at DESC, wl.id DESC
        ";

        return self::pdo()->query($sql)->fetchAll();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private static function touchWorkout(int $workoutId): void
    {
        $stmt = self::pdo()->prepare("UPDATE workouts SET updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':id' => $workoutId]);
    }

    private static function touchWorkoutOfExercise(int $exerciseId): void
    {
        $stmt = self::pdo()->prepare(
            "UPDATE workouts SET updated_at = CURRENT_TIMESTAMP WHERE id = (SELECT workout_id FROM exercises WHERE id = :id)"
        );
        $stmt->execute([':id' => $exerciseId]);
    }

    private static function touchWorkoutOfSet(int $setId): void
    {
        $stmt = self::pdo()->prepare(
            "UPDATE workouts SET updated_at = CURRENT_TIMESTAMP
             WHERE id = (SELECT e.workout_id FROM sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.id = :id)"
        );
        $stmt->execute([':id' => $setId]);
    }
}
