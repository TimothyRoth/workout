CREATE TABLE auth_tokens (
                selector TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                validator_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            );

CREATE TABLE exercises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP, position INTEGER NOT NULL DEFAULT 0, notes TEXT NOT NULL DEFAULT '',
                FOREIGN KEY(workout_id) REFERENCES workouts(id) ON DELETE CASCADE
            );

CREATE TABLE logExercises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                log_id INTEGER NOT NULL,
                exercise_id INTEGER,
                name TEXT NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY(log_id) REFERENCES workoutLog(id) ON DELETE CASCADE
            );

CREATE TABLE logSets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                log_exercise_id INTEGER NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                repetitions INTEGER NOT NULL DEFAULT 0,
                unit TEXT NOT NULL DEFAULT 'kg' CHECK (unit IN ('kg', 'bw', 's', 'min')),
                load REAL,
                rest_time INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY(log_exercise_id) REFERENCES logExercises(id) ON DELETE CASCADE
            );

CREATE TABLE "sets" (
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
            );

CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

CREATE TABLE "workoutLog" (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workout_id INTEGER NOT NULL,
                workout_name TEXT,
                workload REAL NOT NULL DEFAULT 0,
                duration INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            , notes TEXT NOT NULL DEFAULT '');

CREATE TABLE workouts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

CREATE INDEX idx_exercises_workout ON exercises(workout_id, position);

CREATE INDEX idx_logExercises_exercise ON logExercises(exercise_id);

CREATE INDEX idx_logExercises_log ON logExercises(log_id, position);

CREATE INDEX idx_logSets_exercise ON logSets(log_exercise_id, position);

CREATE INDEX idx_log_workout_created ON workoutLog(workout_id, created_at);

CREATE INDEX idx_sets_exercise ON sets(exercise_id, position);

