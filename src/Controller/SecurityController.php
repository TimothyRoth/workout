<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class SecurityController extends AbstractController
{
    /** The form posts back to /login, where the firewall's form_login checks the credentials. */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirect('/', Response::HTTP_SEE_OTHER);
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        // Like the error, the username is filled in once, right after the failed attempt.
        if ($request->hasSession()) {
            $request->getSession()->remove(SecurityRequestAttributes::LAST_USERNAME);
        }

        return $this->render('security/login.html.twig', [
            'title' => 'Anmelden',
            'error' => $error,
            'last_username' => $lastUsername,
        ]);
    }

    /** POST only (a GET answers 404); the firewall's logout listener handles it before this runs. */
    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by the logout listener of the firewall.');
    }
}
