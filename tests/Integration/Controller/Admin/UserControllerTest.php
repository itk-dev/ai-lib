<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\Roles;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end admin user-management surface at `/admin/users`.
 *
 * Users are created per-test via `UserManager::createUser()` and
 * rolled back by `dama/doctrine-test-bundle` between tests.
 */
final class UserControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testAnonymousAccessReturnsUnauthorized(): void
    {
        $this->client->request('GET', '/admin/users');

        self::assertResponseStatusCodeSame(401);
    }

    public function testPlainUserGets403(): void
    {
        $this->loginAsApproved('alice@example.test');

        $this->client->request('GET', '/admin/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminSeesEveryUser(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $um->createUser('eve@other.test', 'Eve', 'pw');

        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('alice@example.test', $body);
        self::assertStringContainsString('eve@other.test', $body);
    }

    public function testDomainManagerSeesOnlySameDomainUsers(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('dm@example.test', 'DM', 'pw', [Roles::DOMAIN_MANAGER]);
        $um->createUser('outsider@other.test', 'Outsider', 'pw');

        $this->loginAsApproved('dm@example.test');

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('alice@example.test', $body);
        self::assertStringNotContainsString('outsider@other.test', $body);
    }

    public function testPendingSubrouteRedirectsToFilteredList(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $this->loginAsApproved('admin@example.test');

        $this->client->request('GET', '/admin/users/pending');

        self::assertResponseRedirects('/admin/users?status=pending');
    }

    public function testStatusFilterRendersOnlyMatchingRows(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $um->createUser('pending@example.test', 'Pending User', 'pw', status: UserStatus::Pending);
        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/users?status=pending');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('pending@example.test', $body);
        // Approved baseline alice should not appear in a `pending` filter.
        self::assertStringNotContainsString('alice@example.test', $body);
    }

    public function testInvalidStatusFilterIsTreatedAsNoFilter(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/users?status=garbage');

        self::assertResponseIsSuccessful();
        // alice (approved) is rendered regardless because the filter falls back to null.
        self::assertStringContainsString('alice@example.test', $crawler->filter('body')->text());
    }

    public function testApproveActionFlipsStatusToApproved(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        // Admin must start Approved so they don't appear in the `?status=pending`
        // list and shadow the target's approve form.
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN], status: UserStatus::Approved);
        $target = $um->createUser('target@example.test', 'Target', 'pw', status: UserStatus::Pending);

        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/users?status=pending');
        // Scope to the target's own approve form — the fixture seeds
        // other pending users, so picking by index would race.
        $form = $crawler->filter('form[action$="/'.$target->getId().'/approve"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users?status=pending');

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Approved, $reloaded->getStatus());
    }

    public function testBlockActionFlipsStatusToBlocked(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $target = $um->createUser('target@example.test', 'Target', 'pw');

        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/users');
        $form = $crawler->filter('form[action$="/'.$target->getId().'/block"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users');

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Blocked, $reloaded->getStatus());
    }

    public function testApproveActionRejectsInvalidCsrfToken(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $target = $um->createUser('target@example.test', 'Target', 'pw', status: UserStatus::Pending);

        $this->loginAsApproved('admin@example.test');

        $this->client->request('POST', '/admin/users/'.$target->getId().'/approve', [
            '_token' => 'nope',
        ]);

        self::assertResponseStatusCodeSame(403);

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Pending, $reloaded->getStatus(), 'CSRF rejection must not have flipped the status.');
    }

    public function testBlockActionRejectsInvalidCsrfToken(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN], status: UserStatus::Approved);
        // Target must start Approved so we can assert the CSRF rejection
        // didn't flip it to Blocked.
        $target = $um->createUser('target@example.test', 'Target', 'pw', status: UserStatus::Approved);

        $this->loginAsApproved('admin@example.test');

        $this->client->request('POST', '/admin/users/'.$target->getId().'/block', [
            '_token' => 'nope',
        ]);

        self::assertResponseStatusCodeSame(403);

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Approved, $reloaded->getStatus(), 'CSRF rejection must not have flipped the status.');
    }

    public function testApproveActionDeniedAcrossDomainsForDomainManager(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('dm@example.test', 'DM', 'pw', [Roles::DOMAIN_MANAGER]);
        $target = $um->createUser('foreign@other.test', 'Foreign', 'pw', status: UserStatus::Pending);

        $this->loginAsApproved('dm@example.test');

        // Direct POST against a cross-domain target — the voter on the
        // `IsGranted` attribute fails closed before the controller body runs,
        // regardless of the CSRF token shape.
        $this->client->request('POST', '/admin/users/'.$target->getId().'/approve', [
            '_token' => 'irrelevant',
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Pending, $reloaded->getStatus());
    }

    public function testBackParameterRespectsTheAdminScope(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $um->createUser('victim@example.test', 'Victim', 'pw');
        $this->loginAsApproved('admin@example.test');

        // Crafted POST with an off-site `back` parameter — controller must ignore it.
        $crawler = $this->client->request('GET', '/admin/users');
        /** @var User $target */
        $target = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'victim@example.test']);
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/users/'.$target->getId().'/block', [
            '_token' => $token,
            'back' => 'https://evil.invalid/owned',
        ]);

        self::assertResponseRedirects('/admin/users');
    }

    private function loginAsApproved(string $email): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        \assert(null !== $user, 'Test user must be created before login.');
        $this->client->loginUser($user);
    }
}
