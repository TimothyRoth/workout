<?php

namespace App;

use JetBrains\PhpStorm\NoReturn;

class Utils
{
    public function renderView(string $view, array $params = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        include __DIR__ . '/view/parts/header.php';
        include __DIR__ . '/view/' . $view . '.php';
        include __DIR__ . '/view/parts/footer.php';
    }

    #[NoReturn]
    public function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    #[NoReturn]
    public function redirect(string $url, array $params = [], string $anchor = ''): void
    {
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        if ($anchor !== '') {
            $url .= '#' . rawurlencode($anchor);
        }
        header('Location: ' . $url, true, 303);
        exit;
    }
}
