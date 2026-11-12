<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/** Post/redirect/get: every form action answers 303 to the page it belongs to. */
trait SeeOther
{
    private function seeOther(string $path, array $query = [], string $anchor = ''): RedirectResponse
    {
        if ([] !== $query) {
            $path .= '?'.http_build_query($query);
        }
        if ('' !== $anchor) {
            $path .= '#'.rawurlencode($anchor);
        }

        return new RedirectResponse($path, Response::HTTP_SEE_OTHER);
    }
}
