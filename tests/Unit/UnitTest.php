<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\Unit;
use PHPUnit\Framework\TestCase;

final class UnitTest extends TestCase
{
    public function testBodyweightCountsRepsOnlyAndNeedsNoLoad(): void
    {
        self::assertFalse(Unit::Bodyweight->needsLoad());
        self::assertSame(12.0, Unit::Bodyweight->workload(12, 50.0));
        self::assertSame(640.0, Unit::Kg->workload(8, 80.0));
        self::assertSame(0.0, Unit::Kg->workload(8, null));
    }

    public function testRecordScoreIsTheEstimatedOneRepMaxForWeights(): void
    {
        self::assertEqualsWithDelta(101.33, Unit::Kg->recordScore(8, 80.0), 0.01);
        self::assertSame(0.0, Unit::Kg->recordScore(0, 80.0));
        self::assertSame(12.0, Unit::Bodyweight->recordScore(12, null));
        self::assertSame(45.001, Unit::Seconds->recordScore(1, 45.0));
    }

    public function testLabelsUseGermanNumbers(): void
    {
        self::assertSame('82,5 kg', Unit::Kg->format(82.5));
        self::assertSame('– kg', Unit::Kg->format(null));
        self::assertSame('Körpergewicht', Unit::Bodyweight->format(null));
        self::assertSame('20 min', Unit::Minutes->format(20));
        self::assertSame('KG', Unit::Bodyweight->short(null));
        self::assertSame('45 s', Unit::Seconds->short(45));
    }
}
