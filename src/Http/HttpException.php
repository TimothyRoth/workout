<?php

namespace App\Http;

use RuntimeException;

/**
 * Thrown for client-facing errors; the code is the HTTP status.
 */
class HttpException extends RuntimeException
{
    public static function badRequest(string $message = 'Ungültige Eingabe.'): self
    {
        return new self($message, 400);
    }

    public static function forbidden(string $message = 'Anfrage abgelehnt.'): self
    {
        return new self($message, 403);
    }

    public static function notFound(string $message = 'Nicht gefunden.'): self
    {
        return new self($message, 404);
    }
}
