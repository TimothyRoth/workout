<?php

declare(strict_types=1);

namespace App\Training;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Training calendar for the logs page: a 16-week heatmap of trained days
 * (Monday-first weeks, local dates) plus a weekly streak. Pure function of
 * the log list, so it is easy to test.
 */
final class Calendar
{
    public const WEEKS = 16;

    private const MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    private const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    /**
     * @param list<array{created_at: string, workout_name: string, actual_workload: mixed}> $logs
     * @return array{
     *     weeks: list<list<array{date: string, label: string, count: int, names: list<string>, future: bool, today: bool}>>,
     *     months: list<array{col: int, label: string}>,
     *     streak: int, thisWeek: int, lastFourWeeks: int, total: int
     * }
     */
    public static function build(array $logs, ?DateTimeImmutable $today = null): array
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $today = ($today ?? new DateTimeImmutable('now', $tz))->setTimezone($tz)->setTime(0, 0);

        // sessions per local day
        $days = [];
        foreach ($logs as $log) {
            try {
                $local = (new DateTimeImmutable((string)$log['created_at'], new DateTimeZone('UTC')))->setTimezone($tz);
            } catch (\Exception) {
                continue;
            }
            $key = $local->format('Y-m-d');
            $days[$key]['count'] = ($days[$key]['count'] ?? 0) + 1;
            $days[$key]['names'][] = (string)$log['workout_name'];
        }

        // grid: WEEKS columns ending with the current week, Monday first
        $monday = $today->modify('monday this week');
        $start = $monday->modify('-' . (self::WEEKS - 1) . ' weeks');
        $weeks = [];
        $months = [];
        $lastMonth = null;
        for ($w = 0; $w < self::WEEKS; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $date = $start->modify('+' . ($w * 7 + $d) . ' days');
                $key = $date->format('Y-m-d');
                $week[] = [
                    'date' => $key,
                    'label' => self::WEEKDAYS[$d] . ' ' . $date->format('d.m.Y'),
                    'count' => $days[$key]['count'] ?? 0,
                    'names' => $days[$key]['names'] ?? [],
                    'future' => $date > $today,
                    'today' => $key === $today->format('Y-m-d'),
                ];
            }
            $weeks[] = $week;
            $month = (int)$start->modify('+' . ($w * 7) . ' days')->format('n');
            if ($month !== $lastMonth) {
                // label a month only when at least two of its weeks are visible, so it never overlaps the next one
                if ($lastMonth !== null || $w === 0) {
                    $months[] = ['col' => $w, 'label' => self::MONTHS[$month - 1]];
                }
                $lastMonth = $month;
            }
        }
        if (count($months) >= 2 && $months[1]['col'] - $months[0]['col'] < 2) {
            array_shift($months);
        }

        // weekly streak: consecutive weeks with a session, ending with this week (a week still in progress does not break it)
        $weekHasSession = [];
        foreach ($days as $key => $_) {
            $weekHasSession[(new DateTimeImmutable($key, $tz))->modify('monday this week')->format('Y-m-d')] = true;
        }
        $cursor = $monday;
        $thisWeek = 0;
        foreach ($weeks[self::WEEKS - 1] as $day) {
            $thisWeek += $day['count'];
        }
        if (!isset($weekHasSession[$cursor->format('Y-m-d')])) {
            $cursor = $cursor->modify('-1 week');
        }
        $streak = 0;
        while (isset($weekHasSession[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor = $cursor->modify('-1 week');
        }

        $lastFourWeeks = 0;
        $fourWeeksAgo = $today->modify('-27 days');
        foreach ($days as $key => $day) {
            if (new DateTimeImmutable($key, $tz) >= $fourWeeksAgo) {
                $lastFourWeeks += $day['count'];
            }
        }

        return [
            'weeks' => $weeks,
            'months' => $months,
            'streak' => $streak,
            'thisWeek' => $thisWeek,
            'lastFourWeeks' => $lastFourWeeks,
            'total' => count($logs),
        ];
    }
}
