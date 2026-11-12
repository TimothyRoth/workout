<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ErrorPage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Not logged in: pages bounce to /login (and come back afterwards), the JSON API answers 401.
 */
final class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(private readonly ErrorPage $errorPage)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            // The firewall has just remembered this URL as the target after login; a fetch call is no page to return to.
            if ($request->hasSession()) {
                $this->removeTargetPath($request->getSession(), 'main');
            }

            return $this->errorPage->render($request, 401, 'Nicht angemeldet.');
        }

        return new RedirectResponse('/login', Response::HTTP_SEE_OTHER);
    }
}
