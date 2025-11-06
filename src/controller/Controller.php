<?php

namespace App\controller;

use AltoRouter;
use App\Database;

class Controller
{
    /**
     * @throws \Exception
     */
    public static function run(): void
    {
        $router = new AltoRouter();

        $router->map('GET', '/', function () {
            $workouts = Database::getWorkouts();
            self::renderView('frontpage', $workouts);
        });

        $router->map('GET', '/workout', function () {
            $workoutId = $_GET['workout_id'] ?? null;

            if ($workoutId !== null) {
                $workout = Database::getWorkout($workoutId);;
                $exercises = Database::getExercises($workoutId);

                foreach ($exercises as $index => $exercise) {
                    $exercises[$index]['sets'] = Database::getSets($exercise['id']);
                }

                $params = [
                    'workout' => $workout,
                    'exercises' => $exercises
                ];

                self::renderView('workout', $params);
            }
        });

        $router->map('POST', '/addWorkout', function () use ($router) {
            $name = $_POST['workout_name'] ?? null;

            if ($name !== null) {
                Database::addWorkout($name);
                header('Location: /');
                exit;
            }
        });

        $router->map('POST', '/deleteWorkout', function () use ($router) {
            $workoutId = $_POST['workout_id'];

            if ($workoutId) {
                Database::deleteWorkout($workoutId);
                header('Location: /');
                exit;
            }
        });

        $router->map('POST', '/editWorkout', function () use ($router) {
            $workoutId = $_POST['workout_id'] ?? null;
            $name = $_POST['workout_name'] ?? null;

            if ($workoutId && $name !== null) {
                Database::editWorkout($workoutId, $name);
                header("Location: /");
                exit;
            }
        });

        $router->map('POST', '/addSet', function () use ($router) {
            $workoutId = $_POST['workout_id'] ?? null;
            $exerciseId = $_POST['exercise_id'] ?? null;
            $exerciseName = $_POST['exercise_name'] ?? null;
            $restTime = $_POST['rest_time'] ?? null;
            $repetitions = $_POST['repetitions'] ?? null;
            $measureUnit = $_POST['measure_unit'] ?? null;
            $amount = $_POST['amount'] ?? null;

            if ($workoutId && $exerciseId && $restTime && $repetitions !== null && $measureUnit !== null && $exerciseName && $amount) {
                for($i = 0; $i < $amount; $i++) {
                    Database::addSet($exerciseId, $repetitions, $measureUnit, $restTime);
                }
                header("Location: /workout?workout_id={$workoutId}#$exerciseName");
                exit;
            }
        });

        $router->map('POST', '/editSet', function () use ($router) {
            $workoutId = $_POST['workout_id'] ?? null;
            $exerciseName = $_POST['exercise_name'] ?? null;
            $setId = $_POST['set_id'] ?? null;
            $restTime = $_POST['rest_time'] ?? null;
            $repetitions = $_POST['repetitions'] ?? null;
            $measureUnit = $_POST['measure_unit'] ?? null;

            if ($workoutId && $setId && $restTime && $repetitions && $measureUnit && $exerciseName) {
                var_dump($exerciseName);
                Database::editSet($setId, $repetitions, $measureUnit, $restTime);
                header("Location: /workout?workout_id={$workoutId}#$exerciseName");
                exit;
            }
        });

        $router->map('POST', '/deleteSet', function () use ($router) {
            $workoutId = $_POST['workout_id'];
            $exerciseName = $_POST['exercise_name'] ?? null;
            $setId = $_POST['set_id'] ?? null;

            if ($workoutId && $setId) {
                Database::deleteSet($setId);
                header("Location: /workout?workout_id={$workoutId}#$exerciseName");
                exit;
            }

        });

        $router->map('POST', '/addExercise', function () use ($router) {
            $name = $_POST['exercise_name'] ?? null;
            $workoutId = $_POST['workout_id'] ?? null;

            if ($name && $workoutId) {
                Database::addExercise($name, $workoutId);
                header("Location: /workout?workout_id={$workoutId}#$name");
                exit;
            }
        });

        $router->map('POST', '/deleteExercise', function () use ($router) {
            $workoutId = $_POST['workout_id'] ?? null;
            $exerciseId = $_POST['exercise_id'] ?? null;

            if ($workoutId && $exerciseId) {
                Database::deleteExercise($exerciseId);
                header("Location: /workout?workout_id={$workoutId}");
                exit;
            }
        });

        $router->map('POST', '/editExercise', function () use ($router) {
            $workoutId = $_POST['workout_id'] ?? null;
            $exerciseId = $_POST['exercise_id'] ?? null;
            $name = $_POST['exercise_name'] ?? null;

            if ($exerciseId && $name && $workoutId) {
                Database::editExercise($exerciseId, $name);
                header("Location: /workout?workout_id={$workoutId}#$name");
                exit;
            }
        });

        $match = $router->match();

        if ($match && is_callable($match['target'])) {
            call_user_func_array($match['target'], $match['params']);
        } else {
            self::render404();
        }
    }

    private static function renderView(string $view, array $params = []): void
    {
        include_once __DIR__ . "/../view/parts/header.php";
        include_once __DIR__ . '/../view/' . $view . '.php';
        include_once __DIR__ . "/../view/parts/footer.php";
    }

    private static function render404(): void
    {
        header($_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');
        echo "404 Not Found";
    }

}