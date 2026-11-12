<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/** The German error answer: JSON for the API and fetch calls, otherwise a page in the app layout. */
final class ErrorPage
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(Request $request, int $status, string $message): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/') || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return Json::response(['status' => 'error', 'message' => $message], $status);
        }

        return new Response($this->twig->render('error.html.twig', ['status' => $status, 'message' => $message]), $status);
    }
}
