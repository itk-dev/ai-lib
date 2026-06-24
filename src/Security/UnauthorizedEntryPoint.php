<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Entry point that returns HTTP 401 when an anonymous request hits
 * a protected route.
 *
 * Without this, Symfony's default form-login entry point would
 * answer the unauthorised request with a 302 to the login form.
 * That's the standard browser-app pattern, but it reads as "this
 * resource has temporarily moved" — a misleading signal when the
 * resource is actually protected, not relocated. Returning 401
 * makes the access decision explicit at the HTTP layer.
 *
 * Users still reach the login form by navigating to it directly;
 * there is no automatic bounce.
 */
final class UnauthorizedEntryPoint implements AuthenticationEntryPointInterface
{
    /**
     * Build the 401 response the firewall returns on anonymous access.
     *
     * @param Request                      $request       the incoming request that triggered the entry point
     * @param AuthenticationException|null $authException the underlying authentication failure, when present
     *
     * @return Response empty 401 response
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response('', Response::HTTP_UNAUTHORIZED);
    }
}
