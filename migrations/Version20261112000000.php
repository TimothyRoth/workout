<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

/**
 * The schema the app had before Symfony (its own migrations v1–v5, tracked in PRAGMA user_version).
 * An empty database gets exactly the tables production has (tests/Fixtures/production_schema.sql);
 * an existing v5 database is adopted without a single change.
 */
final class Version20261112000000 extends AbstractMigration
{
    private const TABLES = ['auth_tokens', 'exercises', 'logExercises', 'logSets', 'sets', 'users', 'workoutLog', 'workouts'];

    public function getDescription(): string
    {
        return 'Baseline: schema v5 of the pre-Symfony app';
    }

    public function up(Schema $schema): void
    {
        $existing = $this->connection->fetchFirstColumn(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'doctrine_migration_versions'"
        );

        if ($existing !== []) {
            $version = (int) $this->connection->fetchOne('PRAGMA user_version');
            $this->abortIf($version !== 5, sprintf('The database has schema version %d; only version 5 can be adopted. Upgrade it with the old app first.', $version));
            $missing = array_diff(self::TABLES, $existing);
            $this->abortIf($missing !== [], 'The database is missing the tables ' . implode(', ', $missing) . '.');
            $this->write('Existing schema v5 adopted unchanged.');

            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE workouts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE exercises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                position INTEGER NOT NULL DEFAULT 0,
                notes TEXT NOT NULL DEFAULT '',
                FOREIGN KEY(workout_id) REFERENCES workouts(id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exercise_id INTEGER NOT NULL,
                repetitions INTEGER NOT NULL DEFAULT 0,
                unit TEXT NOT NULL DEFAULT 'kg' CHECK (unit IN ('kg', 'bw', 's', 'min')),
                load REAL,
                rest_time INTEGER NOT NULL DEFAULT 0,
                position INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE workoutLog (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                workout_name TEXT,
                workload REAL NOT NULL DEFAULT 0,
                duration INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                notes TEXT NOT NULL DEFAULT ''
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE logExercises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                log_id INTEGER NOT NULL,
                exercise_id INTEGER,
                name TEXT NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY(log_id) REFERENCES workoutLog(id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE logSets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                log_exercise_id INTEGER NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                repetitions INTEGER NOT NULL DEFAULT 0,
                unit TEXT NOT NULL DEFAULT 'kg' CHECK (unit IN ('kg', 'bw', 's', 'min')),
                load REAL,
                rest_time INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY(log_exercise_id) REFERENCES logExercises(id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        // Remember-me tokens of the old app; unused since the port, kept so a rollback still works.
        $this->addSql(<<<'SQL'
            CREATE TABLE auth_tokens (
                selector TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                validator_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql('CREATE INDEX idx_exercises_workout ON exercises(workout_id, position)');
        $this->addSql('CREATE INDEX idx_sets_exercise ON sets(exercise_id, position)');
        $this->addSql('CREATE INDEX idx_log_workout_created ON workoutLog(workout_id, created_at)');
        $this->addSql('CREATE INDEX idx_logExercises_log ON logExercises(log_id, position)');
        $this->addSql('CREATE INDEX idx_logExercises_exercise ON logExercises(exercise_id)');
        $this->addSql('CREATE INDEX idx_logSets_exercise ON logSets(log_exercise_id, position)');
        // The old app's schema marker, so a rollback to it finds an up-to-date database.
        $this->addSql('PRAGMA user_version = 5');
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('The baseline cannot be reverted; restore a backup instead.');
    }
}
