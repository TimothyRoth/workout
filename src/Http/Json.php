<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/** JSON as the app has always sent it: unescaped umlauts, "application/json; charset=utf-8". */
final class Json
{
    public static function response(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse(null, $status, ['Content-Type' => 'application/json; charset=utf-8']);
        $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        return $response->setData($data);
    }
}
