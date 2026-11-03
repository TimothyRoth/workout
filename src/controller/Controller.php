<?php

namespace App\controller;

use AltoRouter;
use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Security;
use App\Unit;
use App\Utils;

class Controller
{
    private const MAX_SETS_PER_ADD = 10;

    public function __construct(
        private Utils      $utils = new Utils(),
        private Request    $request = new Request(),
        private AltoRouter $router = new AltoRouter(),
    ) {
    }

    public function run(): void
    {
        Security::sendDefaultHeaders();
        Security::assertSameOrigin($this->request);

        $this->mapPages();
        $this->mapWorkoutActions();
        $this->mapExerciseActions();
        $this->mapSetActions();
        $this->mapApi();

        $match = $this->router->match();

        if ($match === false || !is_callable($match['target'])) {
            throw HttpException::notFound('Diese Seite gibt es nicht.');
        }

        call_user_func_array($match['target'], $match['params']);
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    private function mapPages(): void
    {
        $this->router->map('GET', '/', function () {
            $this->utils->renderView('frontpage', ['title' => 'Workouts', 'workouts' => Database::getWorkouts()]);
        });

        $this->router->map('GET', '/logs', function () {
            $this->utils->renderView('log', ['title' => 'Logs', 'logs' => Database::getLogs()]);
        });

        $this->router->map('GET', '/workout', function () {
            $workout = $this->requireWorkout($this->request->int('workout_id'));
            $this->utils->renderView('workout', ['title' => $workout['name'], 'workout' => $workout]);
        });
    }

    // ---------------------------------------------------------------
    // Workouts
    // ---------------------------------------------------------------

    private function mapWorkoutActions(): void
    {
        $this->router->map('POST', '/addWorkout', function () {
            $name = $this->request->string('workout_name');
            Database::addWorkout($name);
            $this->utils->redirect('/');
        });

        $this->router->map('POST', '/editWorkout', function () {
            $workout = $this->requireWorkout($this->request->int('workout_id'));
            $name = $this->request->string('workout_name');
            Database::editWorkout($workout['id'], $name);
            $this->utils->redirect('/');
        });

        $this->router->map('POST', '/deleteWorkout', function () {
            $workout = $this->requireWorkout($this->request->int('workout_id'));
            Database::deleteWorkout($workout['id']);
            $this->utils->redirect('/');
        });

        $this->router->map('POST', '/duplicateWorkout', function () {
            $workout = $this->requireWorkout($this->request->int('workout_id'));
            $newId = Database::duplicateWorkout($workout['id']);
            $this->utils->redirect('/workout', ['workout_id' => $newId]);
        });
    }

    // ---------------------------------------------------------------
    // Exercises
    // ---------------------------------------------------------------

    private function mapExerciseActions(): void
    {
        $this->router->map('POST', '/addExercise', function () {
            $workout = $this->requireWorkout($this->request->int('workout_id'));
            $name = $this->request->string('exercise_name');
            $exerciseId = Database::addExercise($name, $workout['id']);
            $this->redirectToExercise($workout['id'], $exerciseId);
        });

        $this->router->map('POST', '/editExercise', function () {
            $exercise = $this->requireExercise($this->request->int('exercise_id'));
            $name = $this->request->string('exercise_name');
            Database::editExercise($exercise['id'], $name);
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });

        $this->router->map('POST', '/deleteExercise', function () {
            $exercise = $this->requireExercise($this->request->int('exercise_id'));
            Database::deleteExercise($exercise['id']);
            $this->utils->redirect('/workout', ['workout_id' => $exercise['workout_id']]);
        });

        $this->router->map('POST', '/moveExercise', function () {
            $exercise = $this->requireExercise($this->request->int('exercise_id'));
            Database::moveExercise($exercise['id'], $this->direction());
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });
    }

    // ---------------------------------------------------------------
    // Sets
    // ---------------------------------------------------------------

    private function mapSetActions(): void
    {
        $this->router->map('POST', '/addSet', function () {
            $exercise = $this->requireExercise($this->request->int('exercise_id'));
            $amount = $this->request->int('amount', 1, self::MAX_SETS_PER_ADD);
            [$reps, $unit, $load, $rest] = $this->setFields();

            Database::addSets($exercise['id'], $amount, $reps, $unit, $load, $rest);
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });

        $this->router->map('POST', '/editSet', function () {
            $set = $this->requireSet($this->request->int('set_id'));
            $exercise = $this->requireExercise($set['exercise_id']);
            [$reps, $unit, $load, $rest] = $this->setFields();

            Database::editSet($set['id'], $reps, $unit, $load, $rest);
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });

        $this->router->map('POST', '/deleteSet', function () {
            $set = $this->requireSet($this->request->int('set_id'));
            $exercise = $this->requireExercise($set['exercise_id']);

            Database::deleteSet($set['id']);
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });

        $this->router->map('POST', '/moveSet', function () {
            $set = $this->requireSet($this->request->int('set_id'));
            $exercise = $this->requireExercise($set['exercise_id']);

            Database::moveSet($set['id'], $this->direction());
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });

        $this->router->map('POST', '/duplicateSet', function () {
            $set = $this->requireSet($this->request->int('set_id'));
            $exercise = $this->requireExercise($set['exercise_id']);

            Database::duplicateSet($set['id']);
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });
    }

    /** -1 for "up", +1 for "down". */
    private function direction(): int
    {
        return match ($this->request->string('direction', 10)) {
            'up' => -1,
            'down' => 1,
            default => throw HttpException::badRequest('Ungültige Richtung.'),
        };
    }

    /**
     * @return array{0:int,1:Unit,2:?float,3:int} repetitions, unit, load (kg or seconds, null for bodyweight), rest time in seconds
     */
    private function setFields(): array
    {
        $reps = $this->request->int('repetitions', 0, 10000);
        /** @var Unit $unit */
        $unit = $this->request->enum('unit', Unit::class);
        $load = $unit->needsLoad() ? $this->request->number('load', 0, $unit->maxLoad()) : null;
        $rest = $this->request->int('rest_time', 0, 3600);
        return [$reps, $unit, $load, $rest];
    }

    // ---------------------------------------------------------------
    // JSON API (used by the training session)
    // ---------------------------------------------------------------

    private function mapApi(): void
    {
        $this->router->map('GET', '/api/workout/[i:id]', function (string $id) {
            $workout = $this->requireWorkout((int)$id);
            $this->utils->json([
                'status' => 'success',
                'workout' => $workout,
                'last' => Database::getLastPerformance($workout['id']),
            ]);
        });

        $this->router->map('POST', '/api/workout/log', function () {
            $workout = $this->requireWorkout($this->request->int('workout_id'));
            $duration = $this->request->int('duration', 0, 24 * 60);
            $exercises = $this->parseLoggedExercises($this->request->raw('exercises'));

            $id = Database::addLog($workout['id'], $duration, $exercises);
            $this->utils->json(['status' => 'success', 'id' => $id]);
        });
    }

    /**
     * Validate the session's result: a list of exercises, each with the sets
     * that were actually done. Exercises without done sets are dropped.
     *
     * @return list<array{exercise_id:?int, name:string, sets:list<array{repetitions:int, unit:Unit, load:?float, rest_time:int}>}>
     */
    private function parseLoggedExercises(mixed $raw): array
    {
        if (!is_array($raw) || $raw === [] || count($raw) > 100) {
            throw HttpException::badRequest('Keine Übungen übermittelt.');
        }

        $exercises = [];
        foreach (array_values($raw) as $exercise) {
            if (!is_array($exercise)) {
                throw HttpException::badRequest('Übung ist ungültig.');
            }
            $name = trim(preg_replace('/\s+/u', ' ', (string)($exercise['name'] ?? '')) ?? '');
            if ($name === '' || mb_strlen($name) > 100) {
                throw HttpException::badRequest('Übungsname fehlt oder ist zu lang.');
            }
            $exerciseId = filter_var($exercise['exercise_id'] ?? null, FILTER_VALIDATE_INT);
            $rawSets = $exercise['sets'] ?? null;
            if (!is_array($rawSets) || count($rawSets) > 100) {
                throw HttpException::badRequest("Sätze von „{$name}“ sind ungültig.");
            }

            $sets = [];
            foreach ($rawSets as $set) {
                if (!is_array($set)) {
                    throw HttpException::badRequest("Satz von „{$name}“ ist ungültig.");
                }
                $reps = filter_var($set['reps'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000]]);
                $unit = Unit::tryFrom((string)($set['unit'] ?? ''));
                $rest = filter_var($set['rest_time'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3600]]);
                if ($reps === false || $unit === null || $rest === false) {
                    throw HttpException::badRequest("Satz von „{$name}“ hat ungültige Werte.");
                }
                $load = null;
                if ($unit->needsLoad()) {
                    $value = str_replace(',', '.', trim((string)($set['load'] ?? '')));
                    if (!is_numeric($value) || (float)$value < 0 || (float)$value > $unit->maxLoad()) {
                        throw HttpException::badRequest("Satz von „{$name}“ hat eine ungültige Last.");
                    }
                    $load = (float)$value;
                }
                $sets[] = ['repetitions' => $reps, 'unit' => $unit, 'load' => $load, 'rest_time' => $rest];
            }

            if ($sets !== []) {
                $exercises[] = [
                    'exercise_id' => $exerciseId === false ? null : $exerciseId,
                    'name' => $name,
                    'sets' => $sets,
                ];
            }
        }

        if ($exercises === []) {
            throw HttpException::badRequest('Kein abgeschlossener Satz zum Speichern.');
        }
        return $exercises;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function requireWorkout(int $id): array
    {
        return Database::getWorkoutPlan($id) ?? throw HttpException::notFound('Workout nicht gefunden.');
    }

    private function requireExercise(int $id): array
    {
        return Database::getExercise($id) ?? throw HttpException::notFound('Übung nicht gefunden.');
    }

    private function requireSet(int $id): array
    {
        return Database::getSet($id) ?? throw HttpException::notFound('Satz nicht gefunden.');
    }

    private function redirectToExercise(int $workoutId, int $exerciseId): void
    {
        $this->utils->redirect('/workout', ['workout_id' => $workoutId], 'exercise-' . $exerciseId);
    }
}
