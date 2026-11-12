<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ExerciseRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly WorkoutRepository $workouts,
        private readonly Positions $positions,
    ) {
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchAssociative('SELECT id, workout_id, name, notes FROM exercises WHERE id = :id', ['id' => $id]) ?: null;
    }

    /**
     * Every exercise name ever used (plans and logs), one spelling per name,
     * for the name suggestions when adding or renaming an exercise.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->db->fetchFirstColumn('SELECT name FROM exercises UNION SELECT name FROM logExercises') as $name) {
            $name = trim((string) $name);
            $key = mb_strtolower($name);
            if ($name !== '' && !isset($names[$key])) {
                $names[$key] = $name;
            }
        }
        ksort($names, SORT_STRING);

        return array_values($names);
    }

    public function add(string $name, int $workoutId, string $notes = ''): int
    {
        $this->db->executeStatement(
            'INSERT INTO exercises(name, workout_id, notes, position)
             VALUES(:name, :workout_id, :notes, (SELECT COALESCE(MAX(position), 0) + 1 FROM exercises WHERE workout_id = :workout_id))',
            ['name' => $name, 'workout_id' => $workoutId, 'notes' => $notes]
        );
        $id = (int) $this->db->lastInsertId();
        $this->workouts->touch($workoutId);

        return $id;
    }

    public function edit(int $id, string $name, string $notes = ''): int
    {
        $changed = $this->db->executeStatement(
            'UPDATE exercises SET name = :name, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['name' => $name, 'notes' => $notes, 'id' => $id]
        );
        $this->workouts->touchOfExercise($id);

        return $changed;
    }

    /** Its sets go with it (ON DELETE CASCADE). */
    public function delete(int $id): int
    {
        $this->workouts->touchOfExercise($id);

        return $this->db->executeStatement('DELETE FROM exercises WHERE id = :id', ['id' => $id]);
    }

    /**
     * New order of a workout's exercises; false when $ids is not exactly the current set of exercises.
     *
     * @param list<int|string> $ids
     */
    public function reorder(int $workoutId, array $ids): bool
    {
        if (!$this->positions->reorder('exercises', 'workout_id', $workoutId, $ids)) {
            return false;
        }
        $this->workouts->touch($workoutId);

        return true;
    }
}
