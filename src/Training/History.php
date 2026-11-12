<?php

declare(strict_types=1);

namespace App\Training;

use App\Enum\Unit;

/**
 * Pure helpers over the nested log list that LogRepository::findAll() returns.
 */
final class History
{
    /**
     * Group every logged exercise by identity (exercise id, or workout + name
     * for logs older than schema v3) so a log can show how one exercise
     * developed over time.
     *
     * @return array<string, list<array{log_id:int, created_at:string, sets:array, volume:float}>> newest first
     */
    public static function byExercise(array $logs): array
    {
        $groups = [];
        foreach ($logs as $log) {
            foreach ($log['exercises'] as $exercise) {
                $groups[self::key($log, $exercise)][] = [
                    'log_id' => (int)$log['id'],
                    'created_at' => (string)$log['created_at'],
                    'sets' => $exercise['sets'],
                    'volume' => self::volume($exercise['sets']),
                ];
            }
        }
        return $groups;
    }

    public static function key(array $log, array $exercise): string
    {
        if (!empty($exercise['exercise_id'])) {
            return 'id:' . $exercise['exercise_id'];
        }
        return 'name:' . $log['workout_id'] . ':' . mb_strtolower(trim((string)$exercise['name']));
    }

    public static function volume(array $sets): float
    {
        $total = 0.0;
        foreach ($sets as $set) {
            $unit = Unit::tryFrom((string)($set['unit'] ?? '')) ?? Unit::Kg;
            $total += $unit->workload((int)($set['repetitions'] ?? 0), isset($set['load']) ? (float)$set['load'] : null);
        }
        return $total;
    }

    /**
     * The best set of a logged exercise by Unit::recordScore(), or null when
     * the sets mix units (then "best" has no single meaning).
     * @return array{score: float, label: string, unit: Unit}|null
     */
    public static function bestSet(array $sets): ?array
    {
        $best = null;
        $units = [];
        foreach ($sets as $set) {
            $unit = Unit::tryFrom((string)($set['unit'] ?? '')) ?? Unit::Kg;
            $units[$unit->value] = true;
            $load = isset($set['load']) ? (float)$set['load'] : null;
            $score = $unit->recordScore((int)($set['repetitions'] ?? 0), $load);
            if ($score > 0 && ($best === null || $score > $best['score'])) {
                $best = ['score' => $score, 'label' => (int)$set['repetitions'] . ' × ' . $unit->short($load), 'unit' => $unit];
            }
        }
        return count($units) === 1 ? $best : null;
    }

    /**
     * Chart data for one exercise's history (oldest first): the best set per
     * session (only when every session used the same unit) and the volume.
     * Each series is null when it has fewer than two points.
     *
     * @return array{best: ?array, volume: ?array}
     */
    public static function chartSeries(array $history): array
    {
        $entries = array_reverse($history);
        $bestPoints = [];
        $volumePoints = [];
        $units = [];
        foreach ($entries as $entry) {
            $date = (string)$entry['created_at'];
            $volumePoints[] = ['d' => $date, 'v' => round((float)$entry['volume'], 1), 'label' => self::setsLine($entry['sets'])];
            $best = self::bestSet($entry['sets']);
            if ($best !== null) {
                $units[$best['unit']->value] = $best['unit'];
                $bestPoints[] = ['d' => $date, 'v' => round($best['score'], 1), 'label' => $best['label']];
            }
        }

        $bestSeries = null;
        if (count($units) === 1 && count($bestPoints) >= 2) {
            $unit = reset($units);
            [$title, $format] = match ($unit) {
                Unit::Kg => ['Bestes Set · geschätztes 1RM', 'kg'],
                Unit::Bodyweight => ['Bestes Set · Wiederholungen', ''],
                Unit::Seconds => ['Bestes Set · Dauer', 's'],
                Unit::Minutes => ['Bestes Set · Dauer', 'min'],
            };
            $bestSeries = ['title' => $title, 'unit' => $format, 'points' => $bestPoints];
        }

        return [
            'best' => $bestSeries,
            'volume' => count($volumePoints) >= 2 ? ['title' => 'Volumen', 'unit' => '', 'points' => $volumePoints] : null,
        ];
    }

    /** "8 × 80 kg, 8 × 80 kg, 7 × 80 kg" */
    public static function setsLine(array $sets): string
    {
        return implode(', ', array_map(static function (array $set): string {
            $unit = Unit::tryFrom((string)($set['unit'] ?? '')) ?? Unit::Kg;
            return (int)($set['repetitions'] ?? 0) . ' × ' . $unit->short($set['load'] ?? null);
        }, $sets));
    }
}
