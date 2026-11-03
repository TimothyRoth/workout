<?php

namespace App;

require_once __DIR__ . '/helpers.php';

use App\controller\Controller;
use App\Http\HttpException;
use Throwable;

class Application
{
    public static function run(): void
    {
        date_default_timezone_set('Europe/Berlin');
        mb_internal_encoding('UTF-8');

        try {
            Database::connect();
            (new Controller())->run();
        } catch (HttpException $e) {
            self::renderError($e->getCode(), $e->getMessage());
        } catch (Throwable $e) {
            error_log(sprintf('[workout] %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            self::renderError(500, 'Da ist etwas schiefgelaufen. Der Fehler wurde protokolliert.');
        }
    }

    private static function renderError(int $status, string $message): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($status);

        if (self::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
            return;
        }

        (new Utils())->renderView('error', ['status' => $status, 'message' => $message]);
    }

    private static function wantsJson(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_starts_with($uri, '/api/') || str_contains($accept, 'application/json');
    }
}
