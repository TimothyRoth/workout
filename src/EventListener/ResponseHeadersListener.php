<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;

/**
 * Security headers on every response, and nothing but the service worker is stored by the browser
 * (no stale list after pressing back), as before the port. Runs before the session listener and tells
 * it to leave Cache-Control alone.
 */
#[AsEventListener(priority: -100)]
final class ResponseHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');

        $headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        // Only where the controller did not choose its own caching (the default is "no-cache, private").
        if ('app_service_worker' !== $request->attributes->get('_route') && 'no-cache, private' === $headers->get('Cache-Control')) {
            $headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $headers->set('Expires', 'Thu, 19 Nov 1981 08:52:00 GMT');
            $headers->set('Pragma', 'no-cache');
        }
    }
}
