<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\UserError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * CSRF protection without tokens: every state-changing request must come from our own origin.
 * Browsers always send Origin (or at least Referer) on cross-site POSTs. Runs before the router and
 * the firewall, so a foreign POST is rejected before anything else happens.
 */
#[AsEventListener(priority: 40)]
final class SameOriginListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !\in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $expectedHost = strtolower((string) $request->headers->get('Host'));
        $source = $request->headers->get('Origin') ?? $request->headers->get('Referer');
        if ('' === $expectedHost || null === $source || 'null' === $source) {
            throw UserError::forbidden('Anfrage ohne gültige Herkunft abgelehnt.');
        }

        $sourceHost = strtolower((string) parse_url($source, \PHP_URL_HOST));
        $sourcePort = parse_url($source, \PHP_URL_PORT);
        if (null !== $sourcePort && false !== $sourcePort) {
            $sourceHost .= ':'.$sourcePort;
        }
        if ($sourceHost !== $expectedHost) {
            throw UserError::forbidden('Anfrage von fremder Herkunft abgelehnt.');
        }
    }
}
