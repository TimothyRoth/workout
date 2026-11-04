<?php

namespace App;

use JetBrains\PhpStorm\NoReturn;

class Utils
{
    /**
     * Render a view inside the shared layout. $params['title'] becomes the page title.
     */
    public function renderView(string $view, array $params = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $title = (string)($params['title'] ?? '');
        include __DIR__ . '/view/parts/header.php';
        include __DIR__ . '/view/' . $view . '.php';
        include __DIR__ . '/view/parts/footer.php';
    }

    /**
     * Serve the service worker from src/view/pwa/sw.js.php. Must not be cached by the
     * browser (the SW update check needs the fresh file) and is scoped to "/".
     */
    public function renderServiceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: /');
        include __DIR__ . '/view/pwa/sw.js.php';
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
