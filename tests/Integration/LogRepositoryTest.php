<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\Unit;
use App\Repository\LogRepository;
use App\Repository\WorkoutRepository;
use App\Tests\DatabaseTestCase;

final class LogRepositoryTest extends DatabaseTestCase
{
    private LogRepository $logs;
    private int $workout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logs = $this->service(LogRepository::class);
        $this->workout = $this->service(WorkoutRepository::class)->add('Push');
    }

    public function testTheWorkloadIsComputedOnTheServer(): void
    {
        $id = $this->logSession([['repetitions' => 8, 'unit' => Unit::Kg, 'load' => 80.0, 'rest_time' => 120], ['repetitions' => 12, 'unit' => Unit::Bodyweight, 'load' => 50.0, 'rest_time' => 60]]);

        self::assertSame(652.0, $this->db->fetchOne('SELECT workload FROM workoutLog WHERE id = ?', [$id]));
        self::assertSame('Push', $this->db->fetchOne('SELECT workout_name FROM workoutLog WHERE id = ?', [$id]));
        self::assertNull($this->db->fetchOne("SELECT load FROM logSets WHERE unit = 'bw'"));
    }

    public function testLastPerformanceAndRecords(): void
    {
        $this->logSession([['repetitions' => 8, 'unit' => Unit::Kg, 'load' => 80.0, 'rest_time' => 120]]);
        $this->logSession([['repetitions' => 5, 'unit' => Unit::Kg, 'load' => 80.0, 'rest_time' => 90]]);

        $last = $this->logs->lastPerformance($this->workout);
        self::assertSame([['repetitions' => 5, 'unit' => 'kg', 'load' => 80.0, 'rest_time' => 90]], $last['exercises']['name:bankdrücken']);
        self::assertSame(400.0, $last['workload']);

        $record = $this->logs->records()['name:bankdrücken|kg'];
        self::assertSame(8, $record['repetitions']);
        self::assertSame(101.33, $record['score']);
        self::assertNull($this->logs->lastPerformance(999999));
    }

    public function testBestBeforeAndPreviousOnlyLookBackwards(): void
    {
        $this->logSession([['repetitions' => 10, 'unit' => Unit::Kg, 'load' => 80.0, 'rest_time' => 0]]);
        $this->logSession([['repetitions' => 5, 'unit' => Unit::Kg, 'load' => 80.0, 'rest_time' => 0]]);

        [$newest, $oldest] = $this->logs->findAll();
        self::assertNull($oldest['best_before']);
        self::assertSame(800.0, $newest['best_before']);
        self::assertSame(800.0, $newest['previous_workload']);
        self::assertSame(0, $newest['workout_deleted']);
        self::assertSame(['Bankdrücken'], array_column($newest['exercises'], 'name'));
    }

    public function testUpdateCorrectsOnlyItsOwnSetsAndRecomputesTheWorkload(): void
    {
        $id = $this->logSession([['repetitions' => 8, 'unit' => Unit::Kg, 'load' => 800.0, 'rest_time' => 60], ['repetitions' => 10, 'unit' => Unit::Bodyweight, 'load' => null, 'rest_time' => 60]]);
        $other = $this->logSession([['repetitions' => 1, 'unit' => Unit::Kg, 'load' => 1.0, 'rest_time' => 0]]);
        [$kg, $bw] = array_map('intval', $this->db->fetchFirstColumn('SELECT ls.id FROM logSets ls JOIN logExercises le ON le.id = ls.log_exercise_id WHERE le.log_id = ? ORDER BY ls.position', [$id]));
        $foreign = (int) $this->db->fetchOne('SELECT ls.id FROM logSets ls JOIN logExercises le ON le.id = ls.log_exercise_id WHERE le.log_id = ?', [$other]);

        $this->logs->update($id, 25, 'korrigiert', [$kg => ['repetitions' => 8, 'load' => 80.5], $bw => ['repetitions' => 11, 'load' => 999.0], $foreign => ['repetitions' => 99, 'load' => 99.0]]);

        self::assertSame(['duration' => 25, 'notes' => 'korrigiert', 'workload' => 655.0], $this->db->fetchAssociative('SELECT duration, notes, workload FROM workoutLog WHERE id = ?', [$id]));
        self::assertNull($this->db->fetchOne('SELECT load FROM logSets WHERE id = ?', [$bw]));
        self::assertSame(1, $this->db->fetchOne('SELECT repetitions FROM logSets WHERE id = ?', [$foreign]));
    }

    public function testDeleteRemovesTheSets(): void
    {
        $id = $this->logSession([['repetitions' => 8, 'unit' => Unit::Kg, 'load' => 80.0, 'rest_time' => 0]]);

        $this->logs->delete($id);

        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM logSets'));
    }

    public function testExportNestsEverythingWithTypedValues(): void
    {
        $this->logSession([['repetitions' => 8, 'unit' => Unit::Kg, 'load' => 80.0, 'rest_time' => 120]]);

        $export = $this->logs->exportAll();

        self::assertSame('workout-export', $export['format']);
        self::assertSame('Push', $export['workouts'][0]['name']);
        self::assertSame(['repetitions' => 8, 'unit' => 'kg', 'load' => 80.0, 'rest_time' => 120], $export['logs'][0]['exercises'][0]['sets'][0]);
        self::assertSame(640.0, $export['logs'][0]['workload']);
    }

    private function logSession(array $sets): int
    {
        return $this->logs->add($this->workout, 42, [['exercise_id' => null, 'name' => 'Bankdrücken', 'sets' => $sets]]);
    }
}
