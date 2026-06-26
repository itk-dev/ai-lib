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
 * End-to-end coverage of the admin settings split: `/admin/settings`
 * is a redirect entry point, `/admin/settings/site` houses the brand
 * identity fields, and `/admin/settings/email` houses the admin
 * recipient. Each form posts back to its own endpoint with its own
 * CSRF intent.
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

    // Tests that an anonymous request to the admin settings entry point returns 401.
    public function testAnonymousAccessReturnsUnauthorized(): void
    {
        $this->client->request('GET', '/admin/settings');

        self::assertResponseStatusCodeSame(401);
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

    // Verifies the entry point at /admin/settings redirects an admin to the site sub-page.
    public function testAdminSettingsRedirectsToSitePage(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/settings');

        self::assertResponseRedirects('/admin/settings/site');
    }

    // Verifies the site form is pre-filled with the currently-stored brand identity values.
    public function testAdminSeesSiteFormPrefilledWithCurrentValues(): void
    {
        $this->loginAsAdmin();
        $settings = self::getContainer()->get(SettingsManager::class);
        $settings->setBrandName('Custom Name');
        $settings->setBrandTagline('Custom tagline');
        $settings->setBrandInitials('CN');

        $crawler = $this->client->request('GET', '/admin/settings/site');

        self::assertResponseIsSuccessful();
        self::assertSame('Custom Name', $crawler->filter('input[name="brand_name"]')->attr('value'));
        self::assertSame('Custom tagline', $crawler->filter('input[name="brand_tagline"]')->attr('value'));
        self::assertSame('CN', $crawler->filter('input[name="brand_initials"]')->attr('value'));
    }

    // Verifies the settings tabs render on the site page with the site tab marked active.
    public function testSitePageRendersTabsWithSiteActive(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/settings/site');

        self::assertResponseIsSuccessful();
        $tabs = $crawler->filter('nav[aria-label="Indstillinger – navigation"] a');
        self::assertCount(2, $tabs);
        $current = $tabs->filter('[aria-current="page"]');
        self::assertCount(1, $current);
        self::assertSame('/admin/settings/site', $current->attr('href'));
    }

    // Verifies the settings tabs render on the email page with the email tab marked active.
    public function testEmailPageRendersTabsWithEmailActive(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/settings/email');

        self::assertResponseIsSuccessful();
        $tabs = $crawler->filter('nav[aria-label="Indstillinger – navigation"] a');
        self::assertCount(2, $tabs);
        $current = $tabs->filter('[aria-current="page"]');
        self::assertCount(1, $current);
        self::assertSame('/admin/settings/email', $current->attr('href'));
    }

    // Tests that submitting valid site settings persists every brand field and redirects back to the form.
    public function testValidSiteSubmitPersistsBrandFields(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/settings/site');
        $form = $crawler->filter('form[action$="/admin/settings/site"]')->form([
            'brand_name' => 'New Brand',
            'brand_tagline' => 'New tagline',
            'brand_initials' => 'NB',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/settings/site');
        $settings = self::getContainer()->get(SettingsManager::class);
        self::assertSame('New Brand', $settings->getBrandName());
        self::assertSame('New tagline', $settings->getBrandTagline());
        self::assertSame('NB', $settings->getBrandInitials());
    }

    // Verifies submitting empty brand values clears the stored override so the env-var fallback wins again.
    public function testEmptySiteSubmitRevertsToEnvDefaults(): void
    {
        $this->loginAsAdmin();
        $settings = self::getContainer()->get(SettingsManager::class);
        $settings->setBrandName('Custom Name');

        $crawler = $this->client->request('GET', '/admin/settings/site');
        $form = $crawler->filter('form[action$="/admin/settings/site"]')->form([
            'brand_name' => '',
            'brand_tagline' => '',
            'brand_initials' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/settings/site');
        // With no stored override, the manager falls back to the env-var
        // default — the value injected via the test container.
        self::assertNotSame('Custom Name', self::getContainer()->get(SettingsManager::class)->getBrandName());
    }

    // Verifies that an invalid CSRF token on the site form returns 403 and never reaches SettingsManager.
    public function testInvalidSiteCsrfTokenIsRejected(): void
    {
        $this->loginAsAdmin();
        self::getContainer()->get(SettingsManager::class)->setBrandName('Untouched');

        $this->client->request('POST', '/admin/settings/site', [
            'brand_name' => 'Hijacked',
            'brand_tagline' => 'x',
            'brand_initials' => 'XX',
            '_token' => 'nope',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('Untouched', self::getContainer()->get(SettingsManager::class)->getBrandName());
    }

    // Verifies the email form is pre-filled with the currently-stored admin recipient value.
    public function testAdminSeesEmailFormPrefilledWithCurrentValue(): void
    {
        $this->loginAsAdmin();
        self::getContainer()->get(SettingsManager::class)->setAdminRecipient('ops@example.test');

        $crawler = $this->client->request('GET', '/admin/settings/email');

        self::assertResponseIsSuccessful();
        $value = $crawler->filter('input[name="admin_recipient"]')->attr('value');
        self::assertSame('ops@example.test', $value);
    }

    // Tests that submitting a valid email persists the value and redirects back to the form.
    public function testValidEmailSubmitPersistsRecipient(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/settings/email');
        $form = $crawler->filter('form[action$="/admin/settings/email"]')->form([
            'admin_recipient' => 'new@example.test',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/settings/email');
        self::assertSame(
            'new@example.test',
            self::getContainer()->get(SettingsManager::class)->getAdminRecipient(),
        );
    }

    // Verifies submitting an empty value clears the configured recipient (the setting goes null).
    public function testEmptyEmailSubmitClearsRecipient(): void
    {
        $this->loginAsAdmin();
        self::getContainer()->get(SettingsManager::class)->setAdminRecipient('ops@example.test');

        $crawler = $this->client->request('GET', '/admin/settings/email');
        $form = $crawler->filter('form[action$="/admin/settings/email"]')->form([
            'admin_recipient' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/settings/email');
        self::assertNull(self::getContainer()->get(SettingsManager::class)->getAdminRecipient());
    }

    // Ensures an invalid email re-renders the form with a 422 status and leaves the stored value unchanged.
    public function testInvalidEmailRerendersFormWithoutPersisting(): void
    {
        $this->loginAsAdmin();
        self::getContainer()->get(SettingsManager::class)->setAdminRecipient('ops@example.test');

        $crawler = $this->client->request('GET', '/admin/settings/email');
        $form = $crawler->filter('form[action$="/admin/settings/email"]')->form([
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

    // Verifies that an invalid CSRF token on the email form returns 403 and never reaches SettingsManager.
    public function testInvalidEmailCsrfTokenIsRejected(): void
    {
        $this->loginAsAdmin();
        self::getContainer()->get(SettingsManager::class)->setAdminRecipient('ops@example.test');

        $this->client->request('POST', '/admin/settings/email', [
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

    private function loginAsAdmin(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $this->loginAsApproved('admin@example.test');
    }

    private function loginAsApproved(string $email): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        \assert(null !== $user, 'Test user must be created before login.');
        $this->client->loginUser($user);
    }
}
