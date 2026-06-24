<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use App\Repository\UserRepository;
use App\Security\Roles;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the {@see \App\Twig\Components\UserMenu} dropdown.
 *
 * Drives the component through the real base template, the
 * Security component, the router (route-not-found path), and the
 * translator so the gating + route-existence checks are exercised
 * against the live container.
 */
final class UserMenuRenderTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    // Tests that anonymous visitors don't get the user menu at all.
    public function testAnonymousDoesNotSeeUserMenu(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-controller~="user-menu"]'));
    }

    // Tests that a plain authenticated user sees their name, the User section, and the "Log out" item.
    public function testPlainUserSeesUserSectionOnly(): void
    {
        $this->loginAs('alice@example.test');

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $menu = $crawler->filter('[data-controller~="user-menu"]');
        self::assertCount(1, $menu);

        $menuText = $menu->text();
        self::assertStringContainsString('Alice', $menuText);
        self::assertStringContainsString('Bruger', $menuText);
        self::assertStringContainsString('Log ud', $menuText);

        // No Admin section for a plain user.
        self::assertStringNotContainsString('Administration', $menuText);
        self::assertStringNotContainsString('Administrér brugere', $menuText);
    }

    // Verifies a ROLE_DOMAIN_MANAGER sees the Admin section with the "Administrér brugere" item.
    public function testDomainManagerSeesAdminUsersItem(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('dm@example.test', 'Domain Manager', 'pw', [Roles::DOMAIN_MANAGER]);

        $this->loginAs('dm@example.test');

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $menuText = $crawler->filter('[data-controller~="user-menu"]')->text();
        self::assertStringContainsString('Administration', $menuText);
        self::assertStringContainsString('Administrér brugere', $menuText);
    }

    // Verifies a ROLE_ADMIN sees the Admin section (ROLE_ADMIN implies ROLE_DOMAIN_MANAGER via the role hierarchy).
    public function testAdminSeesAdminSection(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Site Admin', 'pw', [Roles::ADMIN]);

        $this->loginAs('admin@example.test');

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $menuText = $crawler->filter('[data-controller~="user-menu"]')->text();
        self::assertStringContainsString('Administration', $menuText);
        self::assertStringContainsString('Administrér brugere', $menuText);
    }

    // Ensures the dropdown's trigger has the WAI-ARIA "Menu Button" attributes set.
    public function testTriggerExposesMenuButtonAria(): void
    {
        $this->loginAs('alice@example.test');

        $crawler = $this->client->request('GET', '/');

        $trigger = $crawler->filter('[data-user-menu-target="trigger"]');
        self::assertCount(1, $trigger);
        self::assertSame('menu', $trigger->attr('aria-haspopup'));
        self::assertSame('false', $trigger->attr('aria-expanded'));
    }

    private function loginAs(string $email): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        \assert(null !== $user, 'Test user must exist before login.');
        $this->client->loginUser($user);
    }
}
