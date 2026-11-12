<?php

declare(strict_types=1);

namespace App\Training;

final class Format
{
    /** German number without trailing zeros: "640,5", "12", "1.250". */
    public static function number(float|int|string|null $value, int $decimals = 2): string
    {
        $formatted = number_format((float) $value, $decimals, ',', '.');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}
