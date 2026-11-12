<?php

declare(strict_types=1);

namespace App\Enum;

use App\Training\Format;

/**
 * What a set's load is measured in. Stored as the backing string in sets.unit;
 * the numeric part lives in sets.load (kg or seconds, NULL for bodyweight).
 */
enum Unit: string
{
    case Kg = 'kg';
    case Bodyweight = 'bw';
    case Seconds = 's';
    case Minutes = 'min';

    public function label(): string
    {
        return match ($this) {
            self::Kg => 'kg',
            self::Bodyweight => 'Körpergewicht',
            self::Seconds => 'Sekunden',
            self::Minutes => 'Minuten',
        };
    }

    /** Label of the numeric input that goes with this unit, null when there is none. */
    public function loadLabel(): ?string
    {
        return match ($this) {
            self::Kg => 'Gewicht (kg)',
            self::Seconds => 'Dauer (s)',
            self::Minutes => 'Dauer (min)',
            self::Bodyweight => null,
        };
    }

    /** Increment of the load stepper buttons: a small plate, a few seconds, a minute. */
    public function loadStep(): float
    {
        return match ($this) {
            self::Kg => 2.5,
            self::Seconds => 5,
            self::Minutes => 1,
            self::Bodyweight => 0,
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
            self::Minutes => 1440,
            self::Bodyweight => 0,
        };
    }


    /**
     * How good a set was, comparable within one unit: the estimated one-rep
     * max (Epley) for weights, the reps for bodyweight, the duration for
     * timed sets (reps as tie-breaker). Mirrored in js/session.js.
     */
    public function recordScore(int $reps, ?float $load): float
    {
        return match ($this) {
            self::Kg => $reps > 0 ? (float)$load * (1 + $reps / 30) : 0.0,
            self::Bodyweight => (float)$reps,
            self::Seconds, self::Minutes => (float)$load + $reps / 1000,
        };
    }

    /** Unit symbol behind a load input: "kg", "s", "min"; empty for bodyweight. */
    public function symbol(): string
    {
        return match ($this) {
            self::Bodyweight => '',
            self::Kg => 'kg',
            self::Seconds => 's',
            self::Minutes => 'min',
        };
    }

    /** Short form used in compact set lists: "8 × 80 kg", "12 × KG", "1 × 45 s". */
    public function short(float|int|string|null $load): string
    {
        return $this === self::Bodyweight ? 'KG' : $this->format($load);
    }

    /** Human-readable load, e.g. "80 kg", "60 s", "Körpergewicht". */
    public function format(float|int|string|null $load): string
    {
        return match ($this) {
            self::Bodyweight => $this->label(),
            self::Kg => ($load === null ? '–' : Format::number($load)) . ' kg',
            self::Seconds => ($load === null ? '–' : Format::number($load)) . ' s',
            self::Minutes => ($load === null ? '–' : Format::number($load)) . ' min',
        };
    }

    /** Contribution of one set to the workout's workload. Bodyweight counts reps only. */
    public function workload(int $reps, ?float $load): float
    {
        return $this->needsLoad() ? $reps * (float)($load ?? 0) : $reps;
    }

}
