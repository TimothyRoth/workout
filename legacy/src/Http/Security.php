<?php

namespace App\Http;

/**
 * CSRF protection without a session: every state-changing request must
 * come from our own origin. Browsers always send Origin (or at least
 * Referer) on cross-site POSTs, and the credentials nginx asks for are
 * attached automatically, so this check is what stops a foreign page from
 * deleting workouts.
 */
class Security
{
    public static function assertSameOrigin(Request $request): void
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $expectedHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $source = $request->header('Origin') ?? $request->header('Referer');

        if ($expectedHost === '' || $source === null || $source === 'null') {
            throw HttpException::forbidden('Anfrage ohne gültige Herkunft abgelehnt.');
        }

        $sourceHost = strtolower((string)parse_url($source, PHP_URL_HOST));
        $sourcePort = parse_url($source, PHP_URL_PORT);
        if ($sourcePort !== null) {
            $sourceHost .= ':' . $sourcePort;
        }

        if ($sourceHost !== $expectedHost) {
            throw HttpException::forbidden('Anfrage von fremder Herkunft abgelehnt.');
        }
    }

    public static function sendDefaultHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: SAMEORIGIN');
    }
}
