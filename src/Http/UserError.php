<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A client-facing error whose German message is safe to show. Logged as info: wrong input is not an incident.
 */
#[WithLogLevel(LogLevel::INFO)]
final class UserError extends HttpException
{
    public static function badRequest(string $message = 'Ungültige Eingabe.'): self
    {
        return new self(400, $message);
    }

    public static function unauthorized(string $message = 'Nicht angemeldet.'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'Anfrage abgelehnt.'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'Nicht gefunden.'): self
    {
        return new self(404, $message);
    }
}
