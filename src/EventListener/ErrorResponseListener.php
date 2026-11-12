<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\ErrorPage;
use App\Http\UserError;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Every error the app answers with: the message of a UserError, a German default otherwise.
 * Runs after the firewall (login redirects) and after Symfony has logged the exception.
 * In debug mode unexpected errors keep Symfony's own error page.
 */
#[AsEventListener(priority: -64)]
final class ErrorResponseListener
{
    private const MESSAGES = [
        400 => 'Ungültige Eingabe.',
        401 => 'Nicht angemeldet.',
        403 => 'Anfrage abgelehnt.',
        404 => 'Nicht gefunden.',
    ];

    public function __construct(
        private readonly ErrorPage $errorPage,
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        [$status, $message] = match (true) {
            $exception instanceof UserError => [$exception->getStatusCode(), $exception->getMessage()],
            // Unknown URL or a GET on a form action: the page does not exist.
            $exception instanceof NotFoundHttpException, $exception instanceof MethodNotAllowedHttpException => [404, 'Diese Seite gibt es nicht.'],
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500 => [$exception->getStatusCode(), self::MESSAGES[$exception->getStatusCode()] ?? 'Anfrage abgelehnt.'],
            default => [500, 'Da ist etwas schiefgelaufen. Der Fehler wurde protokolliert.'],
        };

        if (500 === $status && $this->debug) {
            return;
        }

        $event->setResponse($this->errorPage->render($event->getRequest(), $status, $message));
    }
}
