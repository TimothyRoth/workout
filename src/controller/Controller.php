<?php

namespace App\controller;

use AltoRouter;
use App\Database;
use App\Utils;
use Throwable;

class Controller
{
    public function __construct(
        private readonly Utils      $utils = new Utils(),
        private readonly AltoRouter $router = new AltoRouter()
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function run(): void
    {
        $this->router->map('GET', '/', function () {
            $workouts = Database::getWorkouts();
            $this->utils->renderView('frontpage', $workouts);
        });

        $this->router->map('GET', '/logs', function () {
            $this->utils->renderView('log', Database::getLogs());
        });

        $this->router->map('GET', '/workout', function () {
            $workoutId = $this->utils->getHttpQueryParam('workout_id');

            if ($this->utils->nullChecker([$workoutId])) {
                $workout = Database::getWorkout($workoutId);
                $exercises = Database::getExercises($workoutId);

                foreach ($exercises as $index => $exercise) {
                    $exercises[$index]['sets'] = Database::getSets($exercise['id']);
                }

                $params = [
                    'workout' => $workout,
                    'exercises' => $exercises
                ];

                $this->utils->renderView('workout', $params);
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/addWorkout', function () {
            $name = $this->utils->getHttpQueryParam('workout_name');

            if ($this->utils->nullChecker([$name])) {
                Database::addWorkout($name);
                $this->utils->redirect("/");
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/deleteWorkout', function () {
            $workoutId = $this->utils->getHttpQueryParam('workout_id');

            if ($this->utils->nullChecker([$workoutId])) {
                Database::deleteWorkout($workoutId);
                $this->utils->redirect("/");
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/editWorkout', function () {
            $name = $this->utils->getHttpQueryParam('workout_name');
            $workoutId = $this->utils->getHttpQueryParam('workout_id');

            if ($this->utils->nullChecker([$name, $workoutId])) {
                Database::editWorkout($workoutId, $name);
                $this->utils->redirect("/");
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/addSet', function () {
            $workoutId = $this->utils->getHttpQueryParam('workout_id');
            $exerciseId = $this->utils->getHttpQueryParam('exercise_id');
            $exerciseName = $this->utils->getHttpQueryParam('exercise_name');
            $restTime = $this->utils->getHttpQueryParam('rest_time');
            $repetitions = $this->utils->getHttpQueryParam('repetitions');
            $measureUnit = $this->utils->getHttpQueryParam('measure_unit');
            $amount = $this->utils->getHttpQueryParam('amount');

            if ($this->utils->nullChecker([$workoutId, $exerciseId, $exerciseName, $restTime, $measureUnit, $amount, $repetitions])) {
                for ($i = 0; $i < $amount; $i++) {
                    Database::addSet($exerciseId, $repetitions, $measureUnit, $restTime);
                }

                $this->utils->redirect("/workout", ['workout_id' => $workoutId], $exerciseName);
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/editSet', function () {
            $workoutId = $this->utils->getHttpQueryParam('workout_id');
            $exerciseName = $this->utils->getHttpQueryParam('exercise_name');
            $setId = $this->utils->getHttpQueryParam('set_id');
            $restTime = $this->utils->getHttpQueryParam('rest_time');
            $repetitions = $this->utils->getHttpQueryParam('repetitions');
            $measureUnit = $this->utils->getHttpQueryParam('measure_unit');

            if ($this->utils->nullChecker([$workoutId, $exerciseName, $setId, $restTime, $measureUnit, $repetitions])) {
                Database::editSet($setId, $repetitions, $measureUnit, $restTime);
                $this->utils->redirect("/workout", ['workout_id' => $workoutId], $exerciseName);
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/deleteSet', function () {
            $workoutId = $this->utils->getHttpQueryParam('workout_id');
            $exerciseName = $this->utils->getHttpQueryParam('exercise_name');
            $setId = $this->utils->getHttpQueryParam('set_id');

            if ($this->utils->nullChecker([$workoutId, $exerciseName, $setId])) {
                Database::deleteSet($setId);
                $this->utils->redirect("/workout", ['workout_id' => $workoutId], $exerciseName);
            } else {
                $this->utils->render400();
            }

        });

        $this->router->map('POST', '/addExercise', function () {
            $exerciseName = $this->utils->getHttpQueryParam('exercise_name');
            $workoutId = $this->utils->getHttpQueryParam('workout_id');

            if ($this->utils->nullChecker([$workoutId, $exerciseName])) {
                Database::addExercise($exerciseName, $workoutId);
                $this->utils->redirect("/workout", ['workout_id' => $workoutId], $exerciseName);
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/deleteExercise', function () {
            $workoutId = $this->utils->getHttpQueryParam('workout_id');
            $exerciseId = $this->utils->getHttpQueryParam('exercise_id');

            if ($this->utils->nullChecker([$workoutId, $exerciseId])) {
                Database::deleteExercise($exerciseId);
                $this->utils->redirect("/workout", ['workout_id' => $workoutId]);
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/editExercise', function () {
            $workoutId = $this->utils->getHttpQueryParam('workout_id');
            $exerciseName = $this->utils->getHttpQueryParam('exercise_name');
            $exerciseId = $this->utils->getHttpQueryParam('exercise_id');

            if ($this->utils->nullChecker([$workoutId, $exerciseName, $exerciseId])) {
                Database::editExercise($exerciseId, $exerciseName);
                $this->utils->redirect("/workout", ['workout_id' => $workoutId], $exerciseName);
            } else {
                $this->utils->render400();
            }
        });

        $this->router->map('POST', '/api/workout/log', function () {
            try {
                $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

                $workoutId = $data['workout_id'] ?? null;
                $duration = $data['duration'] ?? null;
                $workload = $data['workload'] ?? null;
                $summary = $data['summary'] ?? null;

                if ($this->utils->nullChecker([$workoutId, $duration, $workload, $summary])) {
                    Database::addLog($workoutId, $workload, $duration, $summary);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success'], JSON_THROW_ON_ERROR);
                    exit;
                }

                header('Content-Type: application/json', true, 400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid input'], JSON_THROW_ON_ERROR);
                exit;

            } catch (Throwable $e) {
                header($_SERVER["SERVER_PROTOCOL"] . ' 500 Internal Server Error');
                echo $e->getMessage();
                exit;
            }
        });

        $match = $this->router->match();

        if ($match && is_callable($match['target'])) {
            call_user_func_array($match['target'], $match['params']);
        } else {
            $this->utils->render404();
        }
    }

}