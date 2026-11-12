<?php

declare(strict_types=1);

namespace App\Training;

use App\Enum\Unit;
use App\Http\UserError;

/** Validates what the browser sends after a session and after correcting a logged one. */
final class LoggedSessionParser
{
    /**
     * The session's result: a list of exercises, each with the sets that were actually done.
     * Exercises without done sets are dropped.
     *
     * @return list<array{exercise_id: ?int, name: string, sets: list<array{repetitions: int, unit: Unit, load: ?float, rest_time: int, set_id: ?int}>}>
     */
    public static function exercises(mixed $raw): array
    {
        if (!\is_array($raw) || [] === $raw || \count($raw) > 100) {
            throw UserError::badRequest('Keine Übungen übermittelt.');
        }

        $exercises = [];
        foreach (array_values($raw) as $exercise) {
            if (!\is_array($exercise)) {
                throw UserError::badRequest('Übung ist ungültig.');
            }
            $name = trim(preg_replace('/\s+/u', ' ', \is_scalar($exercise['name'] ?? null) ? (string) $exercise['name'] : '') ?? '');
            if ('' === $name || mb_strlen($name) > 100) {
                throw UserError::badRequest('Übungsname fehlt oder ist zu lang.');
            }
            $exerciseId = filter_var($exercise['exercise_id'] ?? null, \FILTER_VALIDATE_INT);
            $rawSets = $exercise['sets'] ?? null;
            if (!\is_array($rawSets) || \count($rawSets) > 100) {
                throw UserError::badRequest("Sätze von „{$name}“ sind ungültig.");
            }

            $sets = [];
            foreach ($rawSets as $set) {
                if (!\is_array($set)) {
                    throw UserError::badRequest("Satz von „{$name}“ ist ungültig.");
                }
                $reps = filter_var($set['reps'] ?? null, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000]]);
                $unit = \is_scalar($set['unit'] ?? null) ? Unit::tryFrom((string) $set['unit']) : null;
                $rest = filter_var($set['rest_time'] ?? 0, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3600]]);
                if (false === $reps || null === $unit || false === $rest) {
                    throw UserError::badRequest("Satz von „{$name}“ hat ungültige Werte.");
                }
                $load = null;
                if ($unit->needsLoad()) {
                    $value = str_replace(',', '.', trim(\is_scalar($set['load'] ?? null) ? (string) $set['load'] : ''));
                    if (!is_numeric($value) || (float) $value < 0 || (float) $value > $unit->maxLoad()) {
                        throw UserError::badRequest("Satz von „{$name}“ hat eine ungültige Last.");
                    }
                    $load = (float) $value;
                }
                $setId = filter_var($set['set_id'] ?? null, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $sets[] = ['repetitions' => $reps, 'unit' => $unit, 'load' => $load, 'rest_time' => $rest, 'set_id' => false === $setId ? null : $setId];
            }

            if ([] !== $sets) {
                $exercises[] = ['exercise_id' => false === $exerciseId ? null : $exerciseId, 'name' => $name, 'sets' => $sets];
            }
        }

        if ([] === $exercises) {
            throw UserError::badRequest('Kein abgeschlossener Satz zum Speichern.');
        }

        return $exercises;
    }

    /**
     * sets[<log set id>][reps|load] from the log edit sheet → id => [repetitions, load]. The load is
     * kept as entered (null when empty); LogRepository::update() drops it for bodyweight sets.
     *
     * @return array<int, array{repetitions: int, load: ?float}>
     */
    public static function corrections(mixed $raw): array
    {
        if (null === $raw) {
            return [];
        }
        if (!\is_array($raw) || \count($raw) > 500) {
            throw UserError::badRequest('Sätze sind ungültig.');
        }

        $sets = [];
        foreach ($raw as $id => $values) {
            $setId = filter_var($id, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $reps = filter_var(\is_array($values) ? ($values['reps'] ?? null) : null, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000]]);
            if (false === $setId || false === $reps) {
                throw UserError::badRequest('Ein Satz hat ungültige Werte.');
            }
            $loadText = str_replace(',', '.', trim(\is_scalar($values['load'] ?? null) ? (string) $values['load'] : ''));
            $load = null;
            if ('' !== $loadText) {
                if (!is_numeric($loadText) || (float) $loadText < 0 || (float) $loadText > 100000) {
                    throw UserError::badRequest('Ein Satz hat eine ungültige Last.');
                }
                $load = (float) $loadText;
            }
            $sets[$setId] = ['repetitions' => $reps, 'load' => $load];
        }

        return $sets;
    }
}
