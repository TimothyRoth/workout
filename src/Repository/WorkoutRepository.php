<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class WorkoutRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** Every workout with when it was last trained and how often. */
    public function findAllWithStats(): array
    {
        return $this->db->fetchAllAssociative("
            SELECT w.id, w.name,
                   (SELECT MAX(created_at) FROM workoutLog wl WHERE wl.workout_id = w.id) AS last_trained_at,
                   (SELECT COUNT(*) FROM workoutLog wl WHERE wl.workout_id = w.id) AS session_count
            FROM workouts w
            ORDER BY w.id
        ");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchAssociative('SELECT id, name, updated_at FROM workouts WHERE id = :id', ['id' => $id]) ?: null;
    }

    /** Workout with its exercises and sets nested, in display order. */
    public function findPlan(int $id): ?array
    {
        $workout = $this->find($id);
        if ($workout === null) {
            return null;
        }

        $workout['exercises'] = $this->db->fetchAllAssociative(
            'SELECT id, workout_id, name, notes FROM exercises WHERE workout_id = :workout_id ORDER BY position, id',
            ['workout_id' => $id]
        );
        if ($workout['exercises'] === []) {
            return $workout;
        }

        $sets = $this->db->fetchAllAssociative(
            'SELECT id, exercise_id, repetitions, unit, load, rest_time
             FROM sets WHERE exercise_id IN (?)
             ORDER BY exercise_id, position, id',
            [array_column($workout['exercises'], 'id')],
            [ArrayParameterType::INTEGER]
        );

        $setsByExercise = [];
        foreach ($sets as $set) {
            $setsByExercise[$set['exercise_id']][] = $set;
        }
        foreach ($workout['exercises'] as &$exercise) {
            $exercise['sets'] = $setsByExercise[$exercise['id']] ?? [];
        }
        unset($exercise);

        return $workout;
    }

    public function add(string $name): int
    {
        $this->db->executeStatement('INSERT INTO workouts(name) VALUES(:name)', ['name' => $name]);

        return (int) $this->db->lastInsertId();
    }

    public function rename(int $id, string $name): int
    {
        return $this->db->executeStatement('UPDATE workouts SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id', ['name' => $name, 'id' => $id]);
    }

    /** Exercises and sets go with it (ON DELETE CASCADE); logs stay. */
    public function delete(int $id): int
    {
        return $this->db->executeStatement('DELETE FROM workouts WHERE id = :id', ['id' => $id]);
    }

    /** Copy a workout with all exercises and sets. Returns the new id. */
    public function duplicate(int $id): int
    {
        $plan = $this->findPlan($id) ?? throw new \RuntimeException("Workout $id does not exist");

        return $this->db->transactional(function () use ($plan): int {
            $newId = $this->add(mb_substr($plan['name'] . ' (Kopie)', 0, 100));
            foreach ($plan['exercises'] as $i => $exercise) {
                $this->db->executeStatement(
                    'INSERT INTO exercises(name, workout_id, position) VALUES(:name, :workout_id, :position)',
                    ['name' => $exercise['name'], 'workout_id' => $newId, 'position' => $i + 1]
                );
                $exerciseId = (int) $this->db->lastInsertId();
                foreach ($exercise['sets'] as $j => $set) {
                    $this->db->executeStatement(
                        'INSERT INTO sets(exercise_id, repetitions, unit, load, rest_time, position)
                         VALUES(:exercise_id, :repetitions, :unit, :load, :rest_time, :position)',
                        [
                            'exercise_id' => $exerciseId,
                            'repetitions' => $set['repetitions'],
                            'unit' => $set['unit'],
                            'load' => $set['load'],
                            'rest_time' => $set['rest_time'],
                            'position' => $j + 1,
                        ]
                    );
                }
            }

            return $newId;
        });
    }

    /** updated_at tells the session in the browser that its cached plan is outdated. */
    public function touch(int $id): void
    {
        $this->db->executeStatement('UPDATE workouts SET updated_at = CURRENT_TIMESTAMP WHERE id = :id', ['id' => $id]);
    }

    public function touchOfExercise(int $exerciseId): void
    {
        $this->db->executeStatement(
            'UPDATE workouts SET updated_at = CURRENT_TIMESTAMP WHERE id = (SELECT workout_id FROM exercises WHERE id = :id)',
            ['id' => $exerciseId]
        );
    }

    public function touchOfSet(int $setId): void
    {
        $this->db->executeStatement(
            'UPDATE workouts SET updated_at = CURRENT_TIMESTAMP
             WHERE id = (SELECT e.workout_id FROM sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.id = :id)',
            ['id' => $setId]
        );
    }
}
