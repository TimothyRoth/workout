<?php

namespace App;

use PDO;
use RuntimeException;

class Database
{
    private const SCHEMA_VERSION = 4;

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
            if ($version < 2) {
                self::migrateToV2();
            }
            if ($version < 3) {
                self::migrateToV3();
            }
            if ($version < 4) {
                self::migrateToV4();
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

    /**
     * v2: the free-text measure_unit ("90kg", "Körpergewicht") becomes a
     * unit enum plus a numeric load, and rest_time becomes an integer.
     * SQLite cannot change column types in place, so the table is rebuilt.
     */
    private static function migrateToV2(): void
    {
        $db = self::$connection;

        $db->exec("
            CREATE TABLE sets_v2 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exercise_id INTEGER NOT NULL,
                repetitions INTEGER NOT NULL DEFAULT 0,
                unit TEXT NOT NULL DEFAULT 'kg' CHECK (unit IN ('kg', 'bw', 's')),
                load REAL,
                rest_time INTEGER NOT NULL DEFAULT 0,
                position INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
            )
        ");

        $insert = $db->prepare("
            INSERT INTO sets_v2(id, exercise_id, repetitions, unit, load, rest_time, position, created_at, updated_at)
            VALUES (:id, :exercise_id, :repetitions, :unit, :load, :rest_time, :position, :created_at, :updated_at)
        ");

        foreach ($db->query("SELECT * FROM sets ORDER BY id") as $row) {
            [$unit, $load] = Unit::fromLegacy((string)($row['measure_unit'] ?? ''));
            // v2 had no minutes yet; v3 widens the constraint and keeps the value.
            if ($unit === Unit::Minutes) {
                $unit = Unit::Seconds;
                $load = $load === null ? null : $load * 60;
            }
            $insert->execute([
                ':id' => $row['id'],
                ':exercise_id' => $row['exercise_id'],
                ':repetitions' => (int)($row['repetitions'] ?? 0),
                ':unit' => $unit->value,
                ':load' => $load,
                ':rest_time' => (int)($row['rest_time'] ?? 0),
                ':position' => (int)($row['position'] ?? $row['id']),
                ':created_at' => $row['created_at'],
                ':updated_at' => $row['updated_at'],
            ]);
        }

        $db->exec("DROP TABLE sets");
        $db->exec("ALTER TABLE sets_v2 RENAME TO sets");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sets_exercise ON sets(exercise_id, position)");
    }

    /**
     * v3: minutes as a unit (sets rebuilt for the wider CHECK), and logged
     * sets stored in logExercises/logSets instead of a JSON summary blob,
     * so last-time values and per-exercise history can be queried.
     */
    private static function migrateToV3(): void
    {
        $db = self::$connection;
        $units = "'" . implode("', '", Unit::values()) . "'";

        // --- sets: widen the unit constraint ---------------------------
        $db->exec("
            CREATE TABLE sets_v3 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exercise_id INTEGER NOT NULL,
                repetitions INTEGER NOT NULL DEFAULT 0,
                unit TEXT NOT NULL DEFAULT 'kg' CHECK (unit IN ($units)),
                load REAL,
                rest_time INTEGER NOT NULL DEFAULT 0,
                position INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
            )
        ");
        $db->exec("
            INSERT INTO sets_v3(id, exercise_id, repetitions, unit, load, rest_time, position, created_at, updated_at)
            SELECT id, exercise_id, repetitions, unit, load, rest_time, position, created_at, updated_at FROM sets
        ");
        $db->exec("DROP TABLE sets");
        $db->exec("ALTER TABLE sets_v3 RENAME TO sets");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sets_exercise ON sets(exercise_id, position)");

        // --- logs: read the JSON summaries before the column goes away --
        $legacy = $db->query("SELECT id, workout_id, summary FROM workoutLog ORDER BY id")->fetchAll();

        $db->exec("
            CREATE TABLE workoutLog_v3 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                workout_name TEXT,
                workload REAL NOT NULL DEFAULT 0,
                duration INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("
            INSERT INTO workoutLog_v3(id, workout_id, workout_name, workload, duration, created_at, updated_at)
            SELECT id, workout_id, workout_name, workload, duration, created_at, updated_at FROM workoutLog
        ");
        $db->exec("DROP TABLE workoutLog");
        $db->exec("ALTER TABLE workoutLog_v3 RENAME TO workoutLog");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_log_workout_created ON workoutLog(workout_id, created_at)");

        $db->exec("
            CREATE TABLE logExercises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                log_id INTEGER NOT NULL,
                exercise_id INTEGER,
                name TEXT NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY(log_id) REFERENCES workoutLog(id) ON DELETE CASCADE
            )
        ");
        $db->exec("
            CREATE TABLE logSets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                log_exercise_id INTEGER NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                repetitions INTEGER NOT NULL DEFAULT 0,
                unit TEXT NOT NULL DEFAULT 'kg' CHECK (unit IN ($units)),
                load REAL,
                rest_time INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY(log_exercise_id) REFERENCES logExercises(id) ON DELETE CASCADE
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_logExercises_log ON logExercises(log_id, position)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_logExercises_exercise ON logExercises(exercise_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_logSets_exercise ON logSets(log_exercise_id, position)");

        $findExercise = $db->prepare("SELECT id FROM exercises WHERE workout_id = :workout_id AND LOWER(name) = LOWER(:name) LIMIT 1");

        foreach ($legacy as $log) {
            $summary = json_decode((string)$log['summary'], true);
            $exercises = is_array($summary) && isset($summary['exercises']) && is_array($summary['exercises'])
                ? array_values($summary['exercises'])
                : [];

            foreach ($exercises as $position => $exercise) {
                if (!is_array($exercise)) {
                    continue;
                }
                $name = trim((string)($exercise['name'] ?? ''));
                $findExercise->execute([':workout_id' => $log['workout_id'], ':name' => $name]);
                $exerciseId = $findExercise->fetchColumn();

                $sets = [];
                foreach ((is_array($exercise['sets'] ?? null) ? $exercise['sets'] : []) as $set) {
                    if (!is_array($set)) {
                        continue;
                    }
                    if (isset($set['unit']) && ($unit = Unit::tryFrom((string)$set['unit'])) !== null) {
                        $load = $unit->needsLoad() && isset($set['load']) && $set['load'] !== '' ? (float)str_replace(',', '.', (string)$set['load']) : null;
                    } else {
                        [$unit, $load] = Unit::fromLegacy((string)($set['measureUnit'] ?? ''));
                    }
                    $sets[] = [
                        'repetitions' => (int)($set['reps'] ?? 0),
                        'unit' => $unit,
                        'load' => $load,
                        'rest_time' => (int)($set['breaktime'] ?? $set['rest_time'] ?? 0),
                    ];
                }

                self::insertLogExercise((int)$log['id'], $position + 1, $exerciseId === false ? null : (int)$exerciseId, $name === '' ? 'Übung' : $name, $sets);
            }
        }
    }

    /**
     * v4: login. Users are created with bin/console, remember-me tokens
     * back the "Angemeldet bleiben" cookie (selector + hashed validator).
     */
    private static function migrateToV4(): void
    {
        $db = self::$connection;

        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS auth_tokens (
                selector TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                validator_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }

    // ---------------------------------------------------------------
    // Users & remember-me tokens
    // ---------------------------------------------------------------

    public static function findUserByUsername(string $username): ?array
    {
        $stmt = self::pdo()->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public static function findUser(int $id): ?array
    {
        $stmt = self::pdo()->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getUsers(): array
    {
        return self::pdo()->query('SELECT id, username, created_at FROM users ORDER BY username')->fetchAll();
    }

    public static function createUser(string $username, string $passwordHash): int
    {
        $stmt = self::pdo()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, $passwordHash]);
        return (int)self::pdo()->lastInsertId();
    }

    public static function updateUserPassword(int $id, string $passwordHash): void
    {
        self::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $id]);
        self::pdo()->prepare('DELETE FROM auth_tokens WHERE user_id = ?')->execute([$id]);
    }

    public static function deleteUser(int $id): void
    {
        self::pdo()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public static function createAuthToken(string $selector, int $userId, string $validatorHash, string $expiresAt): void
    {
        self::pdo()->prepare('INSERT INTO auth_tokens (selector, user_id, validator_hash, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$selector, $userId, $validatorHash, $expiresAt]);
    }

    public static function findAuthToken(string $selector): ?array
    {
        self::pdo()->exec("DELETE FROM auth_tokens WHERE expires_at < strftime('%Y-%m-%d %H:%M:%S', 'now')");
        $stmt = self::pdo()->prepare('SELECT selector, user_id, validator_hash, expires_at FROM auth_tokens WHERE selector = ?');
        $stmt->execute([$selector]);
        return $stmt->fetch() ?: null;
    }

    public static function deleteAuthToken(string $selector): void
    {
        self::pdo()->prepare('DELETE FROM auth_tokens WHERE selector = ?')->execute([$selector]);
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
            "SELECT id, exercise_id, repetitions, unit, load, rest_time
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

    /**
     * Copy a workout with all exercises and sets. Returns the new id.
     */
    public static function duplicateWorkout(int $id): int
    {
        $plan = self::getWorkoutPlan($id);
        if ($plan === null) {
            throw new RuntimeException("Workout $id does not exist");
        }

        $db = self::pdo();
        $db->beginTransaction();
        try {
            $newId = self::addWorkout(mb_substr($plan['name'] . ' (Kopie)', 0, 100));
            $insertExercise = $db->prepare("INSERT INTO exercises(name, workout_id, position) VALUES(:name, :workout_id, :position)");
            $insertSet = $db->prepare(
                "INSERT INTO sets(exercise_id, repetitions, unit, load, rest_time, position)
                 VALUES(:exercise_id, :repetitions, :unit, :load, :rest_time, :position)"
            );

            foreach ($plan['exercises'] as $i => $exercise) {
                $insertExercise->execute([':name' => $exercise['name'], ':workout_id' => $newId, ':position' => $i + 1]);
                $exerciseId = (int)$db->lastInsertId();
                foreach ($exercise['sets'] as $j => $set) {
                    $insertSet->execute([
                        ':exercise_id' => $exerciseId,
                        ':repetitions' => $set['repetitions'],
                        ':unit' => $set['unit'],
                        ':load' => $set['load'],
                        ':rest_time' => $set['rest_time'],
                        ':position' => $j + 1,
                    ]);
                }
            }
            $db->commit();
            return $newId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
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

    /**
     * Move an exercise one step up (-1) or down (+1) within its workout.
     */
    public static function moveExercise(int $id, int $direction): void
    {
        $exercise = self::getExercise($id);
        if ($exercise === null) {
            return;
        }
        self::moveWithinSiblings('exercises', 'workout_id', (int)$exercise['workout_id'], $id, $direction);
        self::touchWorkout((int)$exercise['workout_id']);
    }

    // ---------------------------------------------------------------
    // Sets
    // ---------------------------------------------------------------

    public static function getSet(int $id): ?array
    {
        $stmt = self::pdo()->prepare("SELECT id, exercise_id, repetitions, unit, load, rest_time, position FROM sets WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function addSets(int $exerciseId, int $amount, int $repetitions, Unit $unit, ?float $load, int $restTime): void
    {
        $db = self::pdo();
        $stmt = $db->prepare(
            "INSERT INTO sets(exercise_id, repetitions, unit, load, rest_time, position)
             VALUES(:exercise_id, :repetitions, :unit, :load, :rest_time,
                    (SELECT COALESCE(MAX(position), 0) + 1 FROM sets WHERE exercise_id = :exercise_id))"
        );

        $db->beginTransaction();
        try {
            for ($i = 0; $i < $amount; $i++) {
                $stmt->execute([
                    ':exercise_id' => $exerciseId,
                    ':repetitions' => $repetitions,
                    ':unit' => $unit->value,
                    ':load' => $unit->needsLoad() ? $load : null,
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

    public static function editSet(int $setId, int $repetitions, Unit $unit, ?float $load, int $restTime): int
    {
        $stmt = self::pdo()->prepare(
            "UPDATE sets
             SET repetitions = :repetitions, unit = :unit, load = :load, rest_time = :rest_time, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            ':repetitions' => $repetitions,
            ':unit' => $unit->value,
            ':load' => $unit->needsLoad() ? $load : null,
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

    /**
     * Insert a copy of a set directly after the original. Returns the new id.
     */
    public static function duplicateSet(int $id): int
    {
        $set = self::getSet($id);
        if ($set === null) {
            throw new RuntimeException("Set $id does not exist");
        }

        $db = self::pdo();
        $db->beginTransaction();
        try {
            $position = self::renumber('sets', 'exercise_id', (int)$set['exercise_id'])[$id];
            $shift = $db->prepare("UPDATE sets SET position = position + 1 WHERE exercise_id = :exercise_id AND position > :position");
            $shift->execute([':exercise_id' => $set['exercise_id'], ':position' => $position]);

            $insert = $db->prepare(
                "INSERT INTO sets(exercise_id, repetitions, unit, load, rest_time, position)
                 VALUES(:exercise_id, :repetitions, :unit, :load, :rest_time, :position)"
            );
            $insert->execute([
                ':exercise_id' => $set['exercise_id'],
                ':repetitions' => $set['repetitions'],
                ':unit' => $set['unit'],
                ':load' => $set['load'],
                ':rest_time' => $set['rest_time'],
                ':position' => $position + 1,
            ]);
            $newId = (int)$db->lastInsertId();
            self::touchWorkoutOfExercise((int)$set['exercise_id']);
            $db->commit();
            return $newId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Move a set one step up (-1) or down (+1) within its exercise.
     */
    public static function moveSet(int $id, int $direction): void
    {
        $set = self::getSet($id);
        if ($set === null) {
            return;
        }
        self::moveWithinSiblings('sets', 'exercise_id', (int)$set['exercise_id'], $id, $direction);
        self::touchWorkoutOfExercise((int)$set['exercise_id']);
    }

    // ---------------------------------------------------------------
    // Logs
    // ---------------------------------------------------------------

    /**
     * Store a finished session. $exercises is a list of
     * ['exercise_id' => ?int, 'name' => string, 'sets' => list of
     * ['repetitions' => int, 'unit' => Unit, 'load' => ?float, 'rest_time' => int]].
     * The workload is computed here from the sets, never taken from the client.
     */
    public static function addLog(int $workoutId, int $duration, array $exercises): int
    {
        $workout = self::getWorkout($workoutId);
        if ($workout === null) {
            throw new RuntimeException("Workout $workoutId does not exist");
        }

        $workload = 0.0;
        foreach ($exercises as $exercise) {
            foreach ($exercise['sets'] as $set) {
                $workload += $set['unit']->workload($set['repetitions'], $set['load']);
            }
        }

        $db = self::pdo();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO workoutLog(workout_id, workout_name, workload, duration)
                 VALUES(:workout_id, :workout_name, :workload, :duration)"
            );
            $stmt->execute([
                ':workout_id' => $workoutId,
                ':workout_name' => $workout['name'],
                ':workload' => $workload,
                ':duration' => $duration,
            ]);
            $logId = (int)$db->lastInsertId();

            foreach (array_values($exercises) as $i => $exercise) {
                self::insertLogExercise($logId, $i + 1, $exercise['exercise_id'], $exercise['name'], $exercise['sets']);
            }

            $db->commit();
            return $logId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function insertLogExercise(int $logId, int $position, ?int $exerciseId, string $name, array $sets): void
    {
        $db = self::pdo();
        $insertExercise = $db->prepare(
            "INSERT INTO logExercises(log_id, exercise_id, name, position) VALUES(:log_id, :exercise_id, :name, :position)"
        );
        $insertExercise->execute([':log_id' => $logId, ':exercise_id' => $exerciseId, ':name' => $name, ':position' => $position]);
        $logExerciseId = (int)$db->lastInsertId();

        $insertSet = $db->prepare(
            "INSERT INTO logSets(log_exercise_id, position, repetitions, unit, load, rest_time)
             VALUES(:log_exercise_id, :position, :repetitions, :unit, :load, :rest_time)"
        );
        foreach (array_values($sets) as $j => $set) {
            $unit = $set['unit'] instanceof Unit ? $set['unit'] : (Unit::tryFrom((string)$set['unit']) ?? Unit::Kg);
            $insertSet->execute([
                ':log_exercise_id' => $logExerciseId,
                ':position' => $j + 1,
                ':repetitions' => (int)$set['repetitions'],
                ':unit' => $unit->value,
                ':load' => $unit->needsLoad() ? $set['load'] : null,
                ':rest_time' => (int)($set['rest_time'] ?? 0),
            ]);
        }
    }

    /**
     * All logs, newest first, each with its exercises and sets nested.
     * previous_workload and best_before are computed only over entries that
     * existed at the time of each log, so an old personal record is not
     * retroactively downgraded by later sessions.
     */
    public static function getLogs(): array
    {
        $logs = self::pdo()->query("
            SELECT
                wl.id,
                wl.workout_id,
                COALESCE(w.name, wl.workout_name, 'Gelöschtes Workout') AS workout_name,
                w.id IS NULL AS workout_deleted,
                wl.workload AS actual_workload,
                wl.duration,
                wl.created_at,
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
        ")->fetchAll();

        $exercisesByLog = [];
        foreach (self::pdo()->query("SELECT id, log_id, exercise_id, name FROM logExercises ORDER BY log_id, position, id") as $row) {
            $row['sets'] = [];
            $exercisesByLog[$row['log_id']][$row['id']] = $row;
        }
        $logOfExercise = [];
        foreach ($exercisesByLog as $logId => $exercises) {
            foreach ($exercises as $exerciseId => $_) {
                $logOfExercise[$exerciseId] = $logId;
            }
        }
        foreach (self::pdo()->query("SELECT log_exercise_id, repetitions, unit, load, rest_time FROM logSets ORDER BY log_exercise_id, position, id") as $set) {
            $logId = $logOfExercise[$set['log_exercise_id']] ?? null;
            if ($logId !== null) {
                $exercisesByLog[$logId][$set['log_exercise_id']]['sets'][] = $set;
            }
        }

        foreach ($logs as &$log) {
            $log['exercises'] = array_values($exercisesByLog[$log['id']] ?? []);
        }
        unset($log);

        return $logs;
    }

    /**
     * What was logged the last time this workout was done: the log's date and
     * workload plus, per exercise, the sets. Exercises are keyed by exercise
     * id when the log knows it, otherwise by lower-cased name.
     */
    public static function getLastPerformance(int $workoutId): ?array
    {
        $stmt = self::pdo()->prepare(
            "SELECT id, workload, duration, created_at FROM workoutLog WHERE workout_id = :workout_id ORDER BY created_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute([':workout_id' => $workoutId]);
        $log = $stmt->fetch();
        if ($log === false) {
            return null;
        }

        $stmt = self::pdo()->prepare("
            SELECT le.exercise_id, le.name, ls.repetitions, ls.unit, ls.load, ls.rest_time
            FROM logExercises le
            JOIN logSets ls ON ls.log_exercise_id = le.id
            WHERE le.log_id = :log_id
            ORDER BY le.position, le.id, ls.position, ls.id
        ");
        $stmt->execute([':log_id' => $log['id']]);

        $exercises = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['exercise_id'] !== null ? 'id:' . $row['exercise_id'] : 'name:' . mb_strtolower(trim((string)$row['name']));
            $exercises[$key][] = [
                'repetitions' => (int)$row['repetitions'],
                'unit' => $row['unit'],
                'load' => $row['load'] === null ? null : (float)$row['load'],
                'rest_time' => (int)$row['rest_time'],
            ];
        }

        return [
            'log_id' => (int)$log['id'],
            'created_at' => $log['created_at'],
            'workload' => (float)$log['workload'],
            'duration' => (int)$log['duration'],
            'exercises' => $exercises,
        ];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Give the siblings sharing $parentColumn = $parentId positions 1..n in
     * their current display order. Returns id => position.
     */
    private static function renumber(string $table, string $parentColumn, int $parentId): array
    {
        $db = self::pdo();
        $stmt = $db->prepare("SELECT id FROM $table WHERE $parentColumn = :parent ORDER BY position, id");
        $stmt->execute([':parent' => $parentId]);
        $update = $db->prepare("UPDATE $table SET position = :position WHERE id = :id");

        $positions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $i => $id) {
            $positions[(int)$id] = $i + 1;
            $update->execute([':position' => $i + 1, ':id' => $id]);
        }
        return $positions;
    }

    private static function moveWithinSiblings(string $table, string $parentColumn, int $parentId, int $id, int $direction): void
    {
        $db = self::pdo();
        $db->beginTransaction();
        try {
            $positions = self::renumber($table, $parentColumn, $parentId);
            $ids = array_keys($positions);
            $index = array_search($id, $ids, true);
            $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

            if ($index !== false && $target >= 0 && $target < count($ids)) {
                $update = $db->prepare("UPDATE $table SET position = :position WHERE id = :id");
                $update->execute([':position' => $target + 1, ':id' => $ids[$index]]);
                $update->execute([':position' => $index + 1, ':id' => $ids[$target]]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

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
