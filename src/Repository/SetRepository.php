<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\Unit;
use Doctrine\DBAL\Connection;

final class SetRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly WorkoutRepository $workouts,
        private readonly Positions $positions,
    ) {
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchAssociative(
            'SELECT id, exercise_id, repetitions, unit, load, rest_time, position FROM sets WHERE id = :id',
            ['id' => $id]
        ) ?: null;
    }

    /** $amount identical sets at the end of the exercise. Bodyweight sets never carry a load. */
    public function addMany(int $exerciseId, int $amount, int $repetitions, Unit $unit, ?float $load, int $restTime): void
    {
        $this->db->transactional(function () use ($exerciseId, $amount, $repetitions, $unit, $load, $restTime): void {
            for ($i = 0; $i < $amount; $i++) {
                $this->db->executeStatement(
                    'INSERT INTO sets(exercise_id, repetitions, unit, load, rest_time, position)
                     VALUES(:exercise_id, :repetitions, :unit, :load, :rest_time,
                            (SELECT COALESCE(MAX(position), 0) + 1 FROM sets WHERE exercise_id = :exercise_id))',
                    [
                        'exercise_id' => $exerciseId,
                        'repetitions' => $repetitions,
                        'unit' => $unit->value,
                        'load' => $unit->needsLoad() ? $load : null,
                        'rest_time' => $restTime,
                    ]
                );
            }
            $this->workouts->touchOfExercise($exerciseId);
        });
    }

    public function edit(int $id, int $repetitions, Unit $unit, ?float $load, int $restTime): int
    {
        $changed = $this->db->executeStatement(
            'UPDATE sets
             SET repetitions = :repetitions, unit = :unit, load = :load, rest_time = :rest_time, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'repetitions' => $repetitions,
                'unit' => $unit->value,
                'load' => $unit->needsLoad() ? $load : null,
                'rest_time' => $restTime,
                'id' => $id,
            ]
        );
        $this->workouts->touchOfSet($id);

        return $changed;
    }

    public function delete(int $id): int
    {
        $this->workouts->touchOfSet($id);

        return $this->db->executeStatement('DELETE FROM sets WHERE id = :id', ['id' => $id]);
    }

    /** Insert a copy of a set directly after the original. Returns the new id. */
    public function duplicate(int $id): int
    {
        $set = $this->find($id) ?? throw new \RuntimeException("Set $id does not exist");
        $exerciseId = (int) $set['exercise_id'];

        return $this->db->transactional(function () use ($id, $set, $exerciseId): int {
            $position = $this->positions->renumber('sets', 'exercise_id', $exerciseId)[$id];
            $this->db->executeStatement(
                'UPDATE sets SET position = position + 1 WHERE exercise_id = :exercise_id AND position > :position',
                ['exercise_id' => $exerciseId, 'position' => $position]
            );
            $this->db->executeStatement(
                'INSERT INTO sets(exercise_id, repetitions, unit, load, rest_time, position)
                 VALUES(:exercise_id, :repetitions, :unit, :load, :rest_time, :position)',
                [
                    'exercise_id' => $exerciseId,
                    'repetitions' => $set['repetitions'],
                    'unit' => $set['unit'],
                    'load' => $set['load'],
                    'rest_time' => $set['rest_time'],
                    'position' => $position + 1,
                ]
            );
            $newId = (int) $this->db->lastInsertId();
            $this->workouts->touchOfExercise($exerciseId);

            return $newId;
        });
    }

    /**
     * New order of an exercise's sets; false when $ids is not exactly the current set of sets.
     *
     * @param list<int|string> $ids
     */
    public function reorder(int $exerciseId, array $ids): bool
    {
        if (!$this->positions->reorder('sets', 'exercise_id', $exerciseId, $ids)) {
            return false;
        }
        $this->workouts->touchOfExercise($exerciseId);

        return true;
    }

    /**
     * Write a session's reps and loads back into the plan ("Plan aktualisieren"). Sets are matched by
     * the set_id the session carried; sets that no longer exist, belong to another workout or changed
     * their unit are skipped. Returns the number of updated sets.
     *
     * @param list<array{sets: list<array{set_id?: ?int, repetitions: int, unit: Unit|string, load: ?float}>}> $exercises
     */
    public function applyPerformance(int $workoutId, array $exercises): int
    {
        return $this->db->transactional(function () use ($workoutId, $exercises): int {
            $updated = 0;
            foreach ($exercises as $exercise) {
                foreach ($exercise['sets'] as $set) {
                    if (empty($set['set_id'])) {
                        continue;
                    }
                    $unit = $set['unit'] instanceof Unit ? $set['unit'] : (Unit::tryFrom((string) $set['unit']) ?? Unit::Kg);
                    $updated += $this->db->executeStatement(
                        'UPDATE sets SET repetitions = :repetitions, load = :load, updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id AND unit = :unit
                           AND exercise_id IN (SELECT id FROM exercises WHERE workout_id = :workout_id)',
                        [
                            'repetitions' => (int) $set['repetitions'],
                            'load' => $unit->needsLoad() ? $set['load'] : null,
                            'id' => (int) $set['set_id'],
                            'unit' => $unit->value,
                            'workout_id' => $workoutId,
                        ]
                    );
                }
            }
            if ($updated > 0) {
                $this->workouts->touch($workoutId);
            }

            return $updated;
        });
    }
}
