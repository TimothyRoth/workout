<?php

namespace App\controller;

use AltoRouter;
use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Security;
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
            $this->utils->renderView('frontpage', ['workouts' => Database::getWorkouts()]);
        });

        $this->router->map('GET', '/logs', function () {
            $this->utils->renderView('log', ['logs' => Database::getLogs()]);
        });

        $this->router->map('GET', '/workout', function () {
            $workout = $this->requireWorkout($this->request->int('workout_id'));
            $this->utils->renderView('workout', ['workout' => $workout]);
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
    }

    // ---------------------------------------------------------------
    // Sets
    // ---------------------------------------------------------------

    private function mapSetActions(): void
    {
        $this->router->map('POST', '/addSet', function () {
            $exercise = $this->requireExercise($this->request->int('exercise_id'));
            $amount = $this->request->int('amount', 1, self::MAX_SETS_PER_ADD);
            [$reps, $unit, $rest] = $this->setFields();

            Database::addSets($exercise['id'], $amount, $reps, $unit, $rest);
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });

        $this->router->map('POST', '/editSet', function () {
            $set = $this->requireSet($this->request->int('set_id'));
            $exercise = $this->requireExercise($set['exercise_id']);
            [$reps, $unit, $rest] = $this->setFields();

            Database::editSet($set['id'], $reps, $unit, $rest);
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });

        $this->router->map('POST', '/deleteSet', function () {
            $set = $this->requireSet($this->request->int('set_id'));
            $exercise = $this->requireExercise($set['exercise_id']);

            Database::deleteSet($set['id']);
            $this->redirectToExercise($exercise['workout_id'], $exercise['id']);
        });
    }

    /**
     * @return array{0:int,1:string,2:string} repetitions, measure unit, rest time in seconds
     */
    private function setFields(): array
    {
        $reps = $this->request->int('repetitions', 0, 10000);
        $unit = $this->request->string('measure_unit', 30);
        $rest = (string)$this->request->int('rest_time', 0, 3600);
        return [$reps, $unit, $rest];
    }

    // ---------------------------------------------------------------
    // JSON API (used by the training session)
    // ---------------------------------------------------------------

    private function mapApi(): void
    {
        $this->router->map('GET', '/api/workout/[i:id]', function (string $id) {
            $workout = $this->requireWorkout((int)$id);
            $this->utils->json(['status' => 'success', 'workout' => $workout]);
        });

        $this->router->map('POST', '/api/workout/log', function () {
            $workout = $this->requireWorkout($this->request->int('workout_id'));
            $duration = $this->request->int('duration', 0, 24 * 60);
            $workload = $this->request->number('workload', 0, 1e9);
            $summary = $this->request->raw('summary');

            if (is_string($summary)) {
                $summary = json_decode($summary, true);
            }
            if (!is_array($summary) || !isset($summary['exercises']) || !is_array($summary['exercises'])) {
                throw HttpException::badRequest('Zusammenfassung fehlt oder ist ungültig.');
            }

            $encoded = json_encode(['exercises' => array_values($summary['exercises'])], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (strlen($encoded) > 200_000) {
                throw HttpException::badRequest('Zusammenfassung ist zu groß.');
            }

            $id = Database::addLog($workout['id'], $workload, $duration, $encoded);
            $this->utils->json(['status' => 'success', 'id' => $id]);
        });
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
