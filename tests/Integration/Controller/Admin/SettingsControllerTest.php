<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Repository\UserRepository;
use App\Security\Roles;
use App\Security\UserManager;
use App\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the admin settings page at `/admin/settings`.
 *
 * Users are created per-test via `UserManager::createUser()` and
 * rolled back by `dama/doctrine-test-bundle` between tests.
 */
final class SettingsControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    // Tests that an anonymous request is redirected to the login page.
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/settings');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    // Tests that a plain authenticated user is 403'd — the route is ROLE_ADMIN.
    public function testPlainUserGets403(): void
    {
        $this->loginAsApproved('alice@example.test');

        $this->client->request('GET', '/admin/settings');

        self::assertResponseStatusCodeSame(403);
    }

    // Tests that a domain manager (no ROLE_ADMIN) is 403'd.
    public function testDomainManagerGets403(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('dm@example.test', 'DM', 'pw', [Roles::DOMAIN_MANAGER]);
        $this->loginAsApproved('dm@example.test');

        $this->client->request('GET', '/admin/settings');

        self::assertResponseStatusCodeSame(403);
    }

    // Verifies an admin sees the form pre-filled with the currently-stored admin recipient value.
    public function testAdminSeesFormPrefilledWithCurrentValue(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        self::getContainer()->get(SettingsManager::class)->setAdminRecipient('ops@example.test');

        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/settings');

        self::assertResponseIsSuccessful();
        $value = $crawler->filter('input[name="admin_recipient"]')->attr('value');
        self::assertSame('ops@example.test', $value);
    }

    // Tests that submitting a valid email persists the value through SettingsManager and redirects back to the form.
    public function testValidSubmitPersistsRecipient(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/settings');
        $form = $crawler->filter('form[action$="/admin/settings"]')->form([
            'admin_recipient' => 'new@example.test',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/settings');

        $manager = self::getContainer()->get(SettingsManager::class);
        self::assertSame('new@example.test', $manager->getAdminRecipient());
    }

    // Verifies submitting an empty value clears the configured recipient (the setting goes null).
    public function testEmptySubmitClearsRecipient(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        self::getContainer()->get(SettingsManager::class)->setAdminRecipient('ops@example.test');
        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/settings');
        $form = $crawler->filter('form[action$="/admin/settings"]')->form([
            'admin_recipient' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/settings');
        self::assertNull(self::getContainer()->get(SettingsManager::class)->getAdminRecipient());
    }

    // Ensures an invalid email re-renders the form with a 422 status and leaves the stored value unchanged.
    public function testInvalidEmailRerendersFormWithoutPersisting(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        self::getContainer()->get(SettingsManager::class)->setAdminRecipient('ops@example.test');
        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/settings');
        $form = $crawler->filter('form[action$="/admin/settings"]')->form([
            'admin_recipient' => 'not-an-email',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            'ops@example.test',
            self::getContainer()->get(SettingsManager::class)->getAdminRecipient(),
            'invalid submit must not overwrite the stored value',
        );
    }

    // Verifies that an invalid CSRF token returns 403 and never reaches the SettingsManager.
    public function testInvalidCsrfTokenIsRejected(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        self::getContainer()->get(SettingsManager::class)->setAdminRecipient('ops@example.test');
        $this->loginAsApproved('admin@example.test');

        $this->client->request('POST', '/admin/settings', [
            'admin_recipient' => 'evil@example.test',
            '_token' => 'nope',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(
            'ops@example.test',
            self::getContainer()->get(SettingsManager::class)->getAdminRecipient(),
            'CSRF rejection must not overwrite the stored value',
        );
    }

    private function loginAsApproved(string $email): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        \assert(null !== $user, 'Test user must be created before login.');
        $this->client->loginUser($user);
    }
}
