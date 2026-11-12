<?php

declare(strict_types=1);

namespace App\Controller;

use App\Asset\AppVersion;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PwaController extends AbstractController
{
    /**
     * The service worker is rendered so its cache name carries the deployed commit: every release
     * activates a fresh cache and drops the old one. Never browser-cached, scoped to "/", public.
     */
    #[Route('/sw.js', name: 'app_service_worker', methods: ['GET'])]
    public function serviceWorker(AppVersion $version): Response
    {
        return $this->render('pwa/sw.js.twig', ['version' => $version->current()], new Response(headers: [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]));
    }
}
