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

        $router->map('GET', '/session', function () {
            $sessionId = $_GET['session_id'] ?? null;

            if($sessionId !== null) {
                $session = Database::getSession($sessionId);
                self::renderView('session', $session);
            }
        });

        $router->map('POST', '/createSession', function () use ($router) {
            $name = $_POST['session_name'] ?? null;

            if ($name !== null) {
                Database::createSession($name);
                header('Location: /');
                exit;
            }
        });

        $router->map('POST', '/deleteSession', function () use ($router) {
            $sessionId = $_POST['session_id'];
            Database::deleteSession($sessionId);
            header('Location: /');
            exit;
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
        include_once __DIR__ . "/../view/header.php";
        include_once __DIR__ . '/../view/' . $view . '.php';
        include_once __DIR__ . "/../view/footer.php";
    }

    private static function render404(): void
    {
        header($_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');
        echo "404 Not Found";
    }

}