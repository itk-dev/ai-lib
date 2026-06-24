<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the default-deny `access_control` rule
 * introduced by issue #97.
 *
 * Every route is gated behind `IS_AUTHENTICATED_FULLY` except a
 * short PUBLIC_ACCESS allow-list (login, logout, registration —
 * the password-reset slot lands when that flow is built). This
 * test asserts both directions: representative gated routes
 * redirect anonymous visitors to `/login`, and every allow-list
 * route stays reachable without a session.
 */
final class AccessControlTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function gatedRouteProvider(): iterable
    {
        yield 'frontpage' => ['/'];
        yield 'catalogue search' => ['/search'];
        yield 'assistant detail' => ['/assistant/1'];
        yield 'admin user list' => ['/admin/users'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicRouteProvider(): iterable
    {
        yield 'login form' => ['/login'];
        yield 'registration form' => ['/register'];
        yield 'registration pending page' => ['/register/pending'];
    }

    // Verifies anonymous visitors hitting a gated route get a 302 to /login.
    #[\PHPUnit\Framework\Attributes\DataProvider('gatedRouteProvider')]
    public function testAnonymousIsRedirectedToLogin(string $path): void
    {
        $this->client->request('GET', $path);

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location, $path.' must redirect anonymous visitors to /login');
    }

    // Tests that every PUBLIC_ACCESS allow-list route renders for anonymous visitors.
    #[\PHPUnit\Framework\Attributes\DataProvider('publicRouteProvider')]
    public function testPublicRouteReachableAnonymously(string $path): void
    {
        $this->client->request('GET', $path);

        self::assertResponseIsSuccessful($path.' must be reachable without authentication');
    }

    // Verifies that an authenticated user can reach a previously-gated route.
    public function testAuthenticatedUserCanReachGatedRoute(): void
    {
        $alice = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'alice@example.test']);
        \assert(null !== $alice);
        $this->client->loginUser($alice);

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    // Tests that the firewall preserves the originally-requested URL in the session so the form_login flow lands the user back on the gated page after signing in.
    public function testFailedAnonymousAccessPreservesTargetUrl(): void
    {
        $this->client->request('GET', '/search?model=gpt-4o');

        self::assertResponseRedirects();
        // The session-stored target URL is what `form_login` uses after a
        // successful login. The Symfony default behaviour we rely on is
        // tested indirectly: log in via the form and confirm the redirect
        // lands on the originally requested URL.
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('form');

        $form = $crawler->filter('form')->form();
        $form['_username'] = 'alice@example.test';
        $form['_password'] = 'password';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/search', $location, 'firewall must redirect back to the original gated URL after login');
    }
}
