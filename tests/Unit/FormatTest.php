<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Training\Format;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    public function testGermanNumbersWithoutTrailingZeros(): void
    {
        self::assertSame('640,5', Format::number(640.5));
        self::assertSame('12', Format::number(12));
        self::assertSame('1.250', Format::number('1250'));
        self::assertSame('0', Format::number(null));
        self::assertSame('101,33', Format::number(101.333));
    }
}
