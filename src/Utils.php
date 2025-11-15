<?php

namespace App;

use JetBrains\PhpStorm\NoReturn;

class Utils
{

    public function renderView(string $view, array $params = []): void
    {
        include_once __DIR__ . "/view/parts/header.php";
        include_once __DIR__ . '/view/' . $view . '.php';
        include_once __DIR__ . "/view/parts/footer.php";
    }

    #[NoReturn]
    public function render404(): void
    {
        header($_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');
        echo "404 Not Found";
        exit;
    }

    #[NoReturn]
    public function render400(): void
    {
        header($_SERVER["SERVER_PROTOCOL"] . ' 400 Bad Request');
        echo "400 Bad Request";
        exit;
    }


    #[NoReturn]
    public function redirect(string $url, $params = [], string $anchor = ""): void
    {
        if(!empty ($params)) {
            $url .= '?' . http_build_query($params);
        }

        if(!empty($anchor)) {
            $url .= "#{$anchor}";
        }

        header("Location: $url");
        exit;
    }

    public function getHttpQueryParam(string $key): ?string
    {
        return ($_POST[$key] ?? $_GET[$key] ?? null);
    }

    public function nullChecker(array $checks): bool
    {
        return !in_array(null, $checks, true);
    }
}