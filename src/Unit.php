<?php

namespace App;

/**
 * What a set's load is measured in. Stored as the backing string in sets.unit;
 * the numeric part lives in sets.load (kg or seconds, NULL for bodyweight).
 */
enum Unit: string
{
    case Kg = 'kg';
    case Bodyweight = 'bw';
    case Seconds = 's';

    public function label(): string
    {
        return match ($this) {
            self::Kg => 'kg',
            self::Bodyweight => 'Körpergewicht',
            self::Seconds => 'Sekunden',
        };
    }

    /** Label of the numeric input that goes with this unit, null when there is none. */
    public function loadLabel(): ?string
    {
        return match ($this) {
            self::Kg => 'Gewicht (kg)',
            self::Seconds => 'Dauer (s)',
            self::Bodyweight => null,
        };
    }

    public function needsLoad(): bool
    {
        return $this !== self::Bodyweight;
    }

    public function maxLoad(): float
    {
        return match ($this) {
            self::Kg => 10000,
            self::Seconds => 86400,
            self::Bodyweight => 0,
        };
    }

    /** Human-readable load, e.g. "80 kg", "60 s", "Körpergewicht". */
    public function format(float|int|string|null $load): string
    {
        return match ($this) {
            self::Bodyweight => $this->label(),
            self::Kg => ($load === null ? '–' : format_number($load)) . ' kg',
            self::Seconds => ($load === null ? '–' : format_number($load)) . ' s',
        };
    }

    /** Contribution of one set to the workout's workload. Bodyweight counts reps only. */
    public function workload(int $reps, ?float $load): float
    {
        return $this->needsLoad() ? $reps * (float)($load ?? 0) : $reps;
    }

    /**
     * Parse the free-text measure_unit that sets used before schema v2
     * ("90kg", "22,5 kg", "Körpergewicht", "60 s"). Unknown text becomes kg
     * without a load so it shows up as "– kg" and gets fixed by hand.
     *
     * @return array{0: self, 1: ?float}
     */
    public static function fromLegacy(string $text): array
    {
        $text = mb_strtolower(trim($text));

        if (preg_match('/körper|koerper|body|^bw$/u', $text)) {
            return [self::Bodyweight, null];
        }
        if (!preg_match('/^(\d+(?:[.,]\d+)?)\s*(.*)$/u', $text, $m)) {
            return [self::Kg, null];
        }

        $load = (float)str_replace(',', '.', $m[1]);
        $suffix = $m[2];

        if (preg_match('/^min/u', $suffix)) {
            return [self::Seconds, $load * 60];
        }
        if (preg_match('/^(s$|sek|sec)/u', $suffix)) {
            return [self::Seconds, $load];
        }
        return [self::Kg, $load];
    }
}
