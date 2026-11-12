<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Training\History;
use PHPUnit\Framework\TestCase;

final class HistoryTest extends TestCase
{
    public function testGroupsByExerciseIdOrByWorkoutAndNameForOldLogs(): void
    {
        $logs = [
            ['id' => 2, 'workout_id' => 1, 'created_at' => '2026-11-02 10:00:00', 'exercises' => [
                ['exercise_id' => 7, 'name' => 'Bank', 'sets' => [['repetitions' => 8, 'unit' => 'kg', 'load' => 80.0]]],
                ['exercise_id' => null, 'name' => ' Plank ', 'sets' => [['repetitions' => 1, 'unit' => 's', 'load' => 45.0]]],
            ]],
            ['id' => 1, 'workout_id' => 1, 'created_at' => '2026-11-01 10:00:00', 'exercises' => [
                ['exercise_id' => 7, 'name' => 'Bank', 'sets' => [['repetitions' => 8, 'unit' => 'kg', 'load' => 75.0]]],
            ]],
        ];

        $groups = History::byExercise($logs);

        self::assertSame([2, 1], array_column($groups['id:7'], 'log_id'));
        self::assertSame([640.0, 600.0], array_column($groups['id:7'], 'volume'));
        self::assertArrayHasKey('name:1:plank', $groups);
    }

    public function testBestSetOnlyWhenAllSetsShareAUnit(): void
    {
        self::assertSame('8 × 80 kg', History::bestSet([['repetitions' => 8, 'unit' => 'kg', 'load' => 80], ['repetitions' => 5, 'unit' => 'kg', 'load' => 70]])['label']);
        self::assertNull(History::bestSet([['repetitions' => 8, 'unit' => 'kg', 'load' => 80], ['repetitions' => 12, 'unit' => 'bw', 'load' => null]]));
    }

    public function testChartSeriesNeedTwoPointsAndSkipBestSetForMixedUnits(): void
    {
        $history = [
            ['log_id' => 3, 'created_at' => '2026-11-03', 'volume' => 700.0, 'sets' => [['repetitions' => 12, 'unit' => 'bw', 'load' => null]]],
            ['log_id' => 2, 'created_at' => '2026-11-02', 'volume' => 640.0, 'sets' => [['repetitions' => 8, 'unit' => 'kg', 'load' => 80]]],
        ];

        $series = History::chartSeries($history);

        self::assertNull($series['best']);
        self::assertSame('Volumen', $series['volume']['title']);
        self::assertSame(['2026-11-02', '2026-11-03'], array_column($series['volume']['points'], 'd'));
        self::assertNull(History::chartSeries([$history[0]])['volume']);
    }
}
