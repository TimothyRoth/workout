<?php

declare(strict_types=1);

namespace App\Training;

/** How a logged session compares with the sessions of the same workout before it. */
final class LogBadges
{
    /**
     * @param array{actual_workload: mixed, best_before: mixed, previous_workload: mixed} $log
     *
     * @return array{best: string, best_class: string, previous: ?string, previous_class: string}
     */
    public static function for(array $log): array
    {
        $current = (float) $log['actual_workload'];
        $best = null === $log['best_before'] ? null : (float) $log['best_before'];
        $previous = null === $log['previous_workload'] ? null : (float) $log['previous_workload'];

        if (null === $best) {
            [$bestLabel, $bestClass] = ['Erster Eintrag', 'badge-neutral'];
        } elseif ($current > $best) {
            [$bestLabel, $bestClass] = ['Neuer Bestwert!', 'badge-gold'];
        } elseif ($current == $best) {
            [$bestLabel, $bestClass] = ['Bestwert eingestellt', 'badge-neutral'];
        } elseif ($best > 0) {
            [$bestLabel, $bestClass] = [number_format(($current - $best) / $best * 100, 1, ',', '').'% vom Bestwert', 'badge-down'];
        } else {
            [$bestLabel, $bestClass] = ['Unter Bestwert', 'badge-down'];
        }

        $previousLabel = null;
        $previousClass = 'badge-neutral';
        if (null !== $previous && null !== $best && $current != $best) {
            if ($previous > 0) {
                $delta = ($current - $previous) / $previous * 100;
                $previousLabel = ($delta >= 0 ? '+' : '').number_format($delta, 1, ',', '').'% zum letzten Mal';
            } else {
                $previousLabel = 'Letztes Mal: 0';
            }
            $previousClass = $current > $previous ? 'badge-up' : ($current < $previous ? 'badge-down' : 'badge-neutral');
        }

        return ['best' => $bestLabel, 'best_class' => $bestClass, 'previous' => $previousLabel, 'previous_class' => $previousClass];
    }
}
