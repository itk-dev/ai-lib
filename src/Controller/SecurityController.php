<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Routes for interactive authentication.
 *
 * `GET/POST /login` renders the login form and surfaces the last
 * authentication error and pre-filled username via Symfony Security's
 * {@see AuthenticationUtils}. The actual credential check is performed
 * by the `form_login` authenticator declared in `security.yaml`, not
 * here — this controller stays in the "inject service → render"
 * shape required by project conventions.
 *
 * `/logout` is wired declaratively in the firewall's `logout` block;
 * the method body is never executed because Symfony intercepts the
 * request and clears the session.
 */
final class SecurityController extends AbstractController
{
    /**
     * Render the login form.
     *
     * @param AuthenticationUtils $authenticationUtils symfony Security helper
     *                                                 providing the previous
     *                                                 login error (if any)
     *                                                 and the last entered
     *                                                 username so the form
     *                                                 can be re-rendered with
     *                                                 user input preserved
     *
     * @return Response the rendered `security/login.html.twig` template
     */
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Placeholder action for the `app_logout` route.
     *
     * The route exists so URL generation (`{{ path('app_logout') }}`)
     * works in templates, but Symfony Security intercepts the request
     * and handles session invalidation before this method is called.
     * The unreachable body throws so that any accidental direct call
     * fails loudly.
     *
     * @throws \LogicException always — the firewall must intercept
     */
    #[Route(path: '/logout', name: 'app_logout', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the logout key on the firewall.');
    }
}
