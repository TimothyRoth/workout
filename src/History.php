<?php

namespace App;

/**
 * Pure helpers over the nested log list that Database::getLogs() returns.
 */
class History
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

    /** "8 × 80 kg, 8 × 80 kg, 7 × 80 kg" */
    public static function setsLine(array $sets): string
    {
        return implode(', ', array_map(static function (array $set): string {
            $unit = Unit::tryFrom((string)($set['unit'] ?? '')) ?? Unit::Kg;
            return (int)($set['repetitions'] ?? 0) . ' × ' . $unit->short($set['load'] ?? null);
        }, $sets));
    }
}
