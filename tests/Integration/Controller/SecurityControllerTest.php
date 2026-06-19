<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Controller\SecurityController;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end login / logout flow against the real `form_login`
 * authenticator wired in `security.yaml`.
 *
 * Relies on the baseline `UserFixtures` (alice + bob with password
 * `password`) loaded by `tests/bootstrap_integration.php`.
 */
final class SecurityControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testLoginPageRenders(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
        self::assertSelectorExists('input[name="_csrf_token"]');
    }

    public function testSuccessfulLoginRedirectsToFrontpage(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = 'alice@example.test';
        $form['_password'] = 'password';
        $this->client->submit($form);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        /** @var User|null $token */
        $token = $this->client->getContainer()->get('security.token_storage')->getToken()?->getUser();
        self::assertInstanceOf(User::class, $token);
        self::assertSame('alice@example.test', $token->getUserIdentifier());
    }

    public function testFailedLoginShowsErrorAndStaysOnLoginPage(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = 'alice@example.test';
        $form['_password'] = 'wrong';
        $this->client->submit($form);

        // Form login redirects back to the login page on failure.
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertNull(
            $this->client->getContainer()->get('security.token_storage')->getToken(),
        );
    }

    public function testLogoutActionThrowsWhenInvokedDirectly(): void
    {
        // The firewall intercepts /logout in production, so the method body
        // is unreachable through HTTP. Calling it directly proves the
        // defensive throw is wired correctly.
        $controller = new SecurityController();

        $this->expectException(\LogicException::class);
        $controller->logout();
    }

    public function testLogoutClearsTheSession(): void
    {
        // Sign in first.
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = 'alice@example.test';
        $form['_password'] = 'password';
        $this->client->submit($form);
        $this->client->followRedirect();

        $this->client->request('GET', '/logout');

        // Symfony intercepts /logout and redirects to the configured target.
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertNull(
            $this->client->getContainer()->get('security.token_storage')->getToken(),
        );
    }

    public function testPendingUserCannotLogIn(): void
    {
        $this->client->getContainer()->get(UserManager::class)->createUser(
            'carol@example.test',
            'Carol',
            'password',
            status: UserStatus::Pending,
        );

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = 'carol@example.test';
        $form['_password'] = 'password';
        $this->client->submit($form);

        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // Localised pending message is rendered on the form.
        self::assertStringContainsString(
            'venter på godkendelse',
            $crawler->filter('body')->text(),
        );
        self::assertNull(
            $this->client->getContainer()->get('security.token_storage')->getToken(),
        );
    }

    public function testBlockedUserCannotLogIn(): void
    {
        $this->client->getContainer()->get(UserManager::class)->createUser(
            'dora@example.test',
            'Dora',
            'password',
            status: UserStatus::Blocked,
        );

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = 'dora@example.test';
        $form['_password'] = 'password';
        $this->client->submit($form);

        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertStringContainsString(
            'spærret',
            $crawler->filter('body')->text(),
        );
        self::assertNull(
            $this->client->getContainer()->get('security.token_storage')->getToken(),
        );
    }
}
