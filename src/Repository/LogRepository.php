<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\Unit;
use Doctrine\DBAL\Connection;

/**
 * Finished sessions: workoutLog (one row per session) with its exercises in logExercises and their
 * sets in logSets. A log keeps the workout's name, so it survives when the workout is deleted.
 */
final class LogRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly WorkoutRepository $workouts,
    ) {
    }

    /**
     * Store a finished session. The workload is computed here from the sets, never taken from the client.
     *
     * @param list<array{exercise_id: ?int, name: string, sets: list<array{repetitions: int, unit: Unit, load: ?float, rest_time: int}>}> $exercises
     */
    public function add(int $workoutId, int $duration, array $exercises, string $notes = ''): int
    {
        $workout = $this->workouts->find($workoutId) ?? throw new \RuntimeException("Workout $workoutId does not exist");

        $workload = 0.0;
        foreach ($exercises as $exercise) {
            foreach ($exercise['sets'] as $set) {
                $workload += $set['unit']->workload($set['repetitions'], $set['load']);
            }
        }

        return $this->db->transactional(function () use ($workoutId, $workout, $workload, $duration, $notes, $exercises): int {
            $this->db->executeStatement(
                'INSERT INTO workoutLog(workout_id, workout_name, workload, duration, notes)
                 VALUES(:workout_id, :workout_name, :workload, :duration, :notes)',
                ['workout_id' => $workoutId, 'workout_name' => $workout['name'], 'workload' => $workload, 'duration' => $duration, 'notes' => $notes]
            );
            $logId = (int) $this->db->lastInsertId();

            foreach (array_values($exercises) as $i => $exercise) {
                $this->insertExercise($logId, $i + 1, $exercise['exercise_id'], $exercise['name'], $exercise['sets']);
            }

            return $logId;
        });
    }

    private function insertExercise(int $logId, int $position, ?int $exerciseId, string $name, array $sets): void
    {
        $this->db->executeStatement(
            'INSERT INTO logExercises(log_id, exercise_id, name, position) VALUES(:log_id, :exercise_id, :name, :position)',
            ['log_id' => $logId, 'exercise_id' => $exerciseId, 'name' => $name, 'position' => $position]
        );
        $logExerciseId = (int) $this->db->lastInsertId();

        foreach (array_values($sets) as $j => $set) {
            $unit = $set['unit'] instanceof Unit ? $set['unit'] : (Unit::tryFrom((string) $set['unit']) ?? Unit::Kg);
            $this->db->executeStatement(
                'INSERT INTO logSets(log_exercise_id, position, repetitions, unit, load, rest_time)
                 VALUES(:log_exercise_id, :position, :repetitions, :unit, :load, :rest_time)',
                [
                    'log_exercise_id' => $logExerciseId,
                    'position' => $j + 1,
                    'repetitions' => (int) $set['repetitions'],
                    'unit' => $unit->value,
                    'load' => $unit->needsLoad() ? $set['load'] : null,
                    'rest_time' => (int) ($set['rest_time'] ?? 0),
                ]
            );
        }
    }

    /**
     * All logs, newest first, each with its exercises and sets nested. previous_workload and
     * best_before only look at entries that existed at the time of each log, so an old personal
     * record is not downgraded by later sessions.
     */
    public function findAll(): array
    {
        $logs = $this->db->fetchAllAssociative("
            SELECT
                wl.id,
                wl.workout_id,
                COALESCE(w.name, wl.workout_name, 'Gelöschtes Workout') AS workout_name,
                w.id IS NULL AS workout_deleted,
                wl.workload AS actual_workload,
                wl.duration,
                wl.notes,
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
        ");

        $exercisesByLog = [];
        $logOfExercise = [];
        foreach ($this->db->fetchAllAssociative('SELECT id, log_id, exercise_id, name FROM logExercises ORDER BY log_id, position, id') as $row) {
            $row['sets'] = [];
            $exercisesByLog[$row['log_id']][$row['id']] = $row;
            $logOfExercise[$row['id']] = $row['log_id'];
        }
        foreach ($this->db->fetchAllAssociative('SELECT id, log_exercise_id, repetitions, unit, load, rest_time FROM logSets ORDER BY log_exercise_id, position, id') as $set) {
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

    public function find(int $id): ?array
    {
        return $this->db->fetchAssociative('SELECT id, workout_id, duration, notes FROM workoutLog WHERE id = :id', ['id' => $id]) ?: null;
    }

    /**
     * Correct a logged session: duration, notes and the reps/load of its sets ($sets = log set id =>
     * ['repetitions' => int, 'load' => ?float]); sets of other logs are ignored. The workload is
     * recomputed from all sets.
     *
     * @param array<int, array{repetitions: int, load: ?float}> $sets
     */
    public function update(int $id, int $duration, string $notes, array $sets): void
    {
        $this->db->transactional(function () use ($id, $duration, $notes, $sets): void {
            foreach ($sets as $setId => $values) {
                $unit = Unit::tryFrom((string) $this->db->fetchOne(
                    'SELECT unit FROM logSets WHERE id = :id AND log_exercise_id IN (SELECT id FROM logExercises WHERE log_id = :log_id)',
                    ['id' => $setId, 'log_id' => $id]
                ));
                if ($unit === null) {
                    continue;
                }
                $this->db->executeStatement(
                    'UPDATE logSets SET repetitions = :repetitions, load = :load
                     WHERE id = :id AND log_exercise_id IN (SELECT id FROM logExercises WHERE log_id = :log_id)',
                    ['repetitions' => (int) $values['repetitions'], 'load' => $unit->needsLoad() ? $values['load'] : null, 'id' => $setId, 'log_id' => $id]
                );
            }

            $workload = 0.0;
            foreach ($this->db->fetchAllAssociative(
                'SELECT ls.repetitions, ls.unit, ls.load FROM logSets ls
                 JOIN logExercises le ON le.id = ls.log_exercise_id WHERE le.log_id = :log_id',
                ['log_id' => $id]
            ) as $set) {
                $unit = Unit::tryFrom((string) $set['unit']) ?? Unit::Kg;
                $workload += $unit->workload((int) $set['repetitions'], $set['load'] === null ? null : (float) $set['load']);
            }

            $this->db->executeStatement(
                'UPDATE workoutLog SET duration = :duration, notes = :notes, workload = :workload, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['duration' => $duration, 'notes' => $notes, 'workload' => $workload, 'id' => $id]
            );
        });
    }

    /** Its exercises and sets go with it (ON DELETE CASCADE). */
    public function delete(int $id): void
    {
        $this->db->executeStatement('DELETE FROM workoutLog WHERE id = :id', ['id' => $id]);
    }

    /**
     * What was logged the last time this workout was done: the log's date and workload plus, per
     * exercise, the sets. Exercises are keyed by exercise id when the log knows it, otherwise by
     * lower-cased name.
     */
    public function lastPerformance(int $workoutId): ?array
    {
        $log = $this->db->fetchAssociative(
            'SELECT id, workload, duration, created_at FROM workoutLog WHERE workout_id = :workout_id ORDER BY created_at DESC, id DESC LIMIT 1',
            ['workout_id' => $workoutId]
        );
        if ($log === false) {
            return null;
        }

        $exercises = [];
        foreach ($this->db->fetchAllAssociative('
            SELECT le.exercise_id, le.name, ls.repetitions, ls.unit, ls.load, ls.rest_time
            FROM logExercises le
            JOIN logSets ls ON ls.log_exercise_id = le.id
            WHERE le.log_id = :log_id
            ORDER BY le.position, le.id, ls.position, ls.id
        ', ['log_id' => $log['id']]) as $row) {
            $key = $row['exercise_id'] !== null ? 'id:' . $row['exercise_id'] : 'name:' . mb_strtolower(trim((string) $row['name']));
            $exercises[$key][] = [
                'repetitions' => (int) $row['repetitions'],
                'unit' => $row['unit'],
                'load' => $row['load'] === null ? null : (float) $row['load'],
                'rest_time' => (int) $row['rest_time'],
            ];
        }

        return [
            'log_id' => (int) $log['id'],
            'created_at' => $log['created_at'],
            'workload' => (float) $log['workload'],
            'duration' => (int) $log['duration'],
            'exercises' => $exercises,
        ];
    }

    /**
     * Best logged set per exercise name and unit, keyed "name:<lower name>|<unit>"
     * (exercises are matched by name across workouts, like the history).
     */
    public function records(): array
    {
        $records = [];
        foreach ($this->db->fetchAllAssociative('
            SELECT le.name, ls.unit, ls.repetitions, ls.load, wl.created_at
            FROM logSets ls
            JOIN logExercises le ON le.id = ls.log_exercise_id
            JOIN workoutLog wl ON wl.id = le.log_id
            ORDER BY wl.created_at, wl.id, le.position, ls.position
        ') as $row) {
            $unit = Unit::tryFrom((string) $row['unit']);
            if ($unit === null) {
                continue;
            }
            $load = $row['load'] === null ? null : (float) $row['load'];
            $score = $unit->recordScore((int) $row['repetitions'], $load);
            if ($score <= 0) {
                continue;
            }
            $key = 'name:' . mb_strtolower(trim((string) $row['name'])) . '|' . $unit->value;
            if (!isset($records[$key]) || $score > $records[$key]['score']) {
                $records[$key] = [
                    'unit' => $unit->value,
                    'repetitions' => (int) $row['repetitions'],
                    'load' => $load,
                    'score' => round($score, 2),
                    'created_at' => $row['created_at'],
                ];
            }
        }

        return $records;
    }

    /** Everything the user owns, nested, for the JSON export (no users, no tokens). */
    public function exportAll(): array
    {
        $workouts = $this->db->fetchAllAssociative('SELECT id, name, created_at, updated_at FROM workouts ORDER BY id');
        $exercises = $this->db->fetchAllAssociative('SELECT id, workout_id, name, notes, position FROM exercises ORDER BY workout_id, position, id');
        $sets = $this->db->fetchAllAssociative('SELECT id, exercise_id, repetitions, unit, load, rest_time, position FROM sets ORDER BY exercise_id, position, id');

        $setsByExercise = [];
        foreach ($sets as $set) {
            $set['load'] = $set['load'] === null ? null : (float) $set['load'];
            $set['repetitions'] = (int) $set['repetitions'];
            $set['rest_time'] = (int) $set['rest_time'];
            $setsByExercise[$set['exercise_id']][] = $set;
        }
        $exercisesByWorkout = [];
        foreach ($exercises as $exercise) {
            $exercise['sets'] = $setsByExercise[$exercise['id']] ?? [];
            $exercisesByWorkout[$exercise['workout_id']][] = $exercise;
        }
        foreach ($workouts as &$workout) {
            $workout['exercises'] = $exercisesByWorkout[$workout['id']] ?? [];
        }
        unset($workout);

        $logs = [];
        foreach (array_reverse($this->findAll()) as $log) {
            $logs[] = [
                'id' => (int) $log['id'],
                'workout_id' => (int) $log['workout_id'],
                'workout_name' => $log['workout_name'],
                'duration' => (int) $log['duration'],
                'workload' => (float) $log['actual_workload'],
                'notes' => $log['notes'] ?? '',
                'created_at' => $log['created_at'],
                'exercises' => array_map(static fn(array $exercise): array => [
                    'exercise_id' => $exercise['exercise_id'] === null ? null : (int) $exercise['exercise_id'],
                    'name' => $exercise['name'],
                    'sets' => array_map(static fn(array $set): array => [
                        'repetitions' => (int) $set['repetitions'],
                        'unit' => $set['unit'],
                        'load' => $set['load'] === null ? null : (float) $set['load'],
                        'rest_time' => (int) $set['rest_time'],
                    ], $exercise['sets']),
                ], $log['exercises']),
            ];
        }

        return [
            'format' => 'workout-export',
            'version' => 1,
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'workouts' => $workouts,
            'logs' => $logs,
        ];
    }
}
