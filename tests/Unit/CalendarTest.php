<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Training\Calendar;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    public function testStreakCountsConsecutiveWeeksUpToThisWeek(): void
    {
        $calendar = Calendar::build([$this->log('2026-11-02'), $this->log('2026-10-28'), $this->log('2026-10-20'), $this->log('2026-10-01')], $this->today());

        self::assertSame(3, $calendar['streak']);
        self::assertSame(1, $calendar['thisWeek']);
        self::assertSame(3, $calendar['lastFourWeeks']);
    }

    public function testAWeekInProgressWithoutTrainingKeepsTheStreak(): void
    {
        $calendar = Calendar::build([$this->log('2026-10-28'), $this->log('2026-10-20')], $this->today());

        self::assertSame(2, $calendar['streak']);
        self::assertSame(0, $calendar['thisWeek']);
    }

    public function testAMissedWeekEndsTheStreak(): void
    {
        self::assertSame(0, Calendar::build([$this->log('2026-10-14')], $this->today())['streak']);
    }

    public function testTheGridIsSixteenMondayFirstWeeksEndingToday(): void
    {
        $calendar = Calendar::build([], $this->today());

        self::assertCount(16, $calendar['weeks']);
        self::assertSame('2026-07-20', $calendar['weeks'][0][0]['date']);
        self::assertTrue($calendar['weeks'][15][2]['today']);
        self::assertTrue($calendar['weeks'][15][3]['future']);
    }

    public function testATrainingLateInTheEveningUtcCountsForTheLocalDay(): void
    {
        $calendar = Calendar::build([['created_at' => '2026-11-03 23:30:00', 'workout_name' => 'Spät', 'actual_workload' => 1]], $this->today());

        self::assertSame(1, $calendar['weeks'][15][2]['count']);
        self::assertSame(['Spät'], $calendar['weeks'][15][2]['names']);
    }

    private function log(string $date): array
    {
        return ['created_at' => $date.' 10:00:00', 'workout_name' => 'X', 'actual_workload' => 1];
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-04 12:00:00');
    }
}
