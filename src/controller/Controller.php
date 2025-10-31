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
            $sessions = Database::getSessions();
            self::renderView('frontpage', $sessions);
        });

        $match = $router->match();

        if($match && is_callable($match['target'])) {
            call_user_func_array($match['target'], $match['params']);
        } else {
            header($_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');
            echo "404 Not Found";
        }
    }

    private static function renderView(string $view, array $params = []): void {
        include_once __DIR__ . "/../view/header.php";
        include_once __DIR__ . '/../view/' . $view . '.php';
        include_once __DIR__ . "/../view/footer.php";
    }
}