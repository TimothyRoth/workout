<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\Unit;
use App\Repository\ExerciseRepository;
use App\Repository\LogRepository;
use App\Repository\SetRepository;
use App\Repository\WorkoutRepository;
use App\Tests\DatabaseTestCase;

final class PlanRepositoriesTest extends DatabaseTestCase
{
    private WorkoutRepository $workouts;
    private ExerciseRepository $exercises;
    private SetRepository $sets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workouts = $this->service(WorkoutRepository::class);
        $this->exercises = $this->service(ExerciseRepository::class);
        $this->sets = $this->service(SetRepository::class);
    }

    public function testThePlanNestsExercisesAndSetsInDisplayOrder(): void
    {
        $workout = $this->workouts->add('Push');
        $bench = $this->exercises->add('Bankdrücken', $workout, "Sitz auf 4\nenger Griff");
        $dips = $this->exercises->add('Dips', $workout);
        $this->sets->addMany($bench, 2, 8, Unit::Kg, 80.0, 120);
        $this->sets->addMany($dips, 1, 12, Unit::Bodyweight, 50.0, 60);

        $plan = $this->workouts->findPlan($workout);

        self::assertSame(['Bankdrücken', 'Dips'], array_column($plan['exercises'], 'name'));
        self::assertSame("Sitz auf 4\nenger Griff", $plan['exercises'][0]['notes']);
        self::assertSame([8, 8], array_column($plan['exercises'][0]['sets'], 'repetitions'));
        self::assertSame(80.0, $plan['exercises'][0]['sets'][0]['load']);
        self::assertNull($plan['exercises'][1]['sets'][0]['load'], 'bodyweight sets never carry a load');
        self::assertNull($this->workouts->findPlan(999999));
    }

    public function testDuplicateCopiesExercisesAndSets(): void
    {
        $workout = $this->workouts->add('Push');
        $bench = $this->exercises->add('Bankdrücken', $workout);
        $this->sets->addMany($bench, 3, 8, Unit::Kg, 80.0, 120);

        $copy = $this->workouts->duplicate($workout);

        $plan = $this->workouts->findPlan($copy);
        self::assertSame('Push (Kopie)', $plan['name']);
        self::assertCount(3, $plan['exercises'][0]['sets']);
    }

    public function testDeletingAWorkoutRemovesItsPlanButKeepsItsLogs(): void
    {
        $workout = $this->workouts->add('Push');
        $bench = $this->exercises->add('Bankdrücken', $workout);
        $this->sets->addMany($bench, 1, 8, Unit::Kg, 80.0, 120);
        $this->service(LogRepository::class)->add($workout, 30, [['exercise_id' => $bench, 'name' => 'Bankdrücken', 'sets' => [['repetitions' => 8, 'unit' => Unit::Kg, 'load' => 80.0, 'rest_time' => 120]]]]);

        $this->workouts->delete($workout);

        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM exercises'));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM sets'));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM logSets'));
        self::assertSame([1], array_column($this->service(LogRepository::class)->findAll(), 'workout_deleted'));
    }

    public function testDuplicateSetInsertsTheCopyRightAfterTheOriginal(): void
    {
        $exercise = $this->exercises->add('Bankdrücken', $this->workouts->add('Push'));
        $this->sets->addMany($exercise, 2, 8, Unit::Kg, 80.0, 120);
        [$first, $second] = $this->setIds($exercise);

        $copy = $this->sets->duplicate($first);

        self::assertSame([$first, $copy, $second], $this->setIds($exercise));
    }

    public function testReorderAcceptsOnlyExactlyTheCurrentSets(): void
    {
        $exercise = $this->exercises->add('Bankdrücken', $this->workouts->add('Push'));
        $this->sets->addMany($exercise, 3, 8, Unit::Kg, 80.0, 120);
        [$a, $b, $c] = $this->setIds($exercise);

        self::assertFalse($this->sets->reorder($exercise, [$c, $a]));
        self::assertFalse($this->sets->reorder($exercise, [$c, $b, $a, 999999]));
        self::assertSame([$a, $b, $c], $this->setIds($exercise));

        self::assertTrue($this->sets->reorder($exercise, [$c, $a, $b]));
        self::assertSame([$c, $a, $b], $this->setIds($exercise));
    }

    public function testReorderExercises(): void
    {
        $workout = $this->workouts->add('Push');
        $first = $this->exercises->add('A', $workout);
        $second = $this->exercises->add('B', $workout);

        self::assertTrue($this->exercises->reorder($workout, [$second, $first]));
        self::assertSame(['B', 'A'], array_column($this->workouts->findPlan($workout)['exercises'], 'name'));
    }

    public function testApplyPerformanceOnlyTouchesMatchingSetsOfTheWorkout(): void
    {
        $workout = $this->workouts->add('Push');
        $exercise = $this->exercises->add('Bankdrücken', $workout);
        $this->sets->addMany($exercise, 1, 8, Unit::Kg, 80.0, 120);
        $other = $this->exercises->add('Fremd', $this->workouts->add('Pull'));
        $this->sets->addMany($other, 1, 8, Unit::Kg, 50.0, 120);
        [$set] = $this->setIds($exercise);
        [$foreign] = $this->setIds($other);

        $updated = $this->sets->applyPerformance($workout, [['sets' => [
            ['set_id' => $set, 'repetitions' => 11, 'unit' => Unit::Kg, 'load' => 85.0],
            ['set_id' => $set, 'repetitions' => 3, 'unit' => Unit::Bodyweight, 'load' => null],
            ['set_id' => $foreign, 'repetitions' => 1, 'unit' => Unit::Kg, 'load' => 1.0],
            ['set_id' => null, 'repetitions' => 1, 'unit' => Unit::Kg, 'load' => 1.0],
        ]]]);

        self::assertSame(1, $updated);
        self::assertSame(['repetitions' => 11, 'load' => 85.0], $this->db->fetchAssociative('SELECT repetitions, load FROM sets WHERE id = ?', [$set]));
        self::assertSame(50.0, $this->db->fetchOne('SELECT load FROM sets WHERE id = ?', [$foreign]));
    }

    public function testExerciseNamesFromPlansAndLogsOncePerSpelling(): void
    {
        $workout = $this->workouts->add('Push');
        $this->exercises->add('Bankdrücken', $workout);
        $this->exercises->add('bankdrücken', $workout);
        $this->service(LogRepository::class)->add($workout, 1, [['exercise_id' => null, 'name' => 'Arnold Press', 'sets' => []]]);

        self::assertSame(['Arnold Press', 'Bankdrücken'], $this->exercises->names());
    }

    public function testEditingThePlanMarksTheWorkoutAsChanged(): void
    {
        $workout = $this->workouts->add('Push');
        $this->db->executeStatement("UPDATE workouts SET updated_at = '2000-01-01 00:00:00'");

        $this->exercises->add('Bankdrücken', $workout);

        self::assertNotSame('2000-01-01 00:00:00', $this->workouts->find($workout)['updated_at']);
    }

    /** @return list<int> */
    private function setIds(int $exercise): array
    {
        return array_map('intval', $this->db->fetchFirstColumn('SELECT id FROM sets WHERE exercise_id = ? ORDER BY position, id', [$exercise]));
    }
}
