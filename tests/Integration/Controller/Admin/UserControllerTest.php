<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\DataFixtures\UserFixtures;
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

    // Verifies the Role column renders with the user's current role label.
    public function testAdminListRendersRoleColumn(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $um->createUser('mgr@example.test', 'Mgr', 'pw', [Roles::DOMAIN_MANAGER]);
        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $headers = $crawler->filter('thead th')->each(fn ($th) => trim($th->text()));
        self::assertContains('Rolle', $headers, 'Role column header must render.');
        // The label cell for the manager row mentions the manager label.
        $bodyText = $crawler->filter('tbody')->text();
        self::assertStringContainsString('Domæne-ansvarlig', $bodyText);
    }

    // Tests that an admin sees the 'Promote to Admin' option in the dropdown.
    public function testAdminSeesPromoteToAdminOption(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $um->createUser('target@example.test', 'Target', 'pw');
        $this->loginAsApproved('admin@example.test');

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $optionLabels = $crawler->filter('select option')->each(fn ($o) => trim($o->text()));
        self::assertContains('Forfrem til administrator', $optionLabels);
    }

    // Tests that a manager does NOT see the 'Promote to Admin' option for any user.
    public function testManagerDoesNotSeePromoteToAdminOption(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('mgr@example.test', 'Mgr', 'pw', [Roles::DOMAIN_MANAGER]);
        $um->createUser('target@example.test', 'Target', 'pw');
        $this->loginAsApproved('mgr@example.test');

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $optionLabels = $crawler->filter('select option')->each(fn ($o) => trim($o->text()));
        self::assertNotContains('Forfrem til administrator', $optionLabels);
    }

    // Verifies the dropdown is omitted entirely for admin rows when the actor is a manager.
    public function testManagerSeesNoDropdownForAdminTargets(): void
    {
        $um = self::getContainer()->get(UserManager::class);
        $um->createUser('mgr@example.test', 'Mgr', 'pw', [Roles::DOMAIN_MANAGER]);
        $um->createUser('inhouse-admin@example.test', 'Inhouse Admin', 'pw', [Roles::ADMIN]);
        $this->loginAsApproved('mgr@example.test');

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        // The manager only sees same-domain rows. The admin row renders
        // its current role label, but no <select> next to it.
        $adminRow = $crawler->filter('tbody tr:contains("inhouse-admin@example.test")');
        self::assertGreaterThan(0, $adminRow->count(), 'Admin row must render for an in-domain manager.');
        self::assertCount(0, $adminRow->filter('select'), 'Manager must not be offered a dropdown on an admin row.');
    }

    // Ensures the block button disappears for admin targets when the actor is a manager — defense in depth against an oversight in the voter. The approve form is naturally absent on Approved targets, so this asserts only on the block path that the voter rule actually gates.
    public function testManagerSeesNoBlockButtonForAdminTargets(): void
    {
        // Both users come from UserFixtures: manager@aarhus.dk holds
        // ROLE_DOMAIN_MANAGER and admin@aarhus.dk holds ROLE_ADMIN,
        // sharing the @aarhus.dk domain — the exact configuration in
        // which the manager-cannot-touch-admin rule has to bite.
        $this->loginAsApproved(UserFixtures::DOMAIN_MANAGER_EMAIL);

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $adminRow = $crawler->filter('tbody tr:contains("'.UserFixtures::ADMIN_EMAIL.'")');
        self::assertGreaterThan(0, $adminRow->count(), 'Admin row must render for an in-domain manager.');
        self::assertCount(0, $adminRow->filter('form[action*="/block"]'), 'Manager must not see the block form on an admin row.');
    }

    // Verifies a hand-crafted POST to /admin/users/{adminId}/block as a manager actor returns 403 and does not flip the admin's status.
    public function testManagerCannotBlockAdminViaDirectPost(): void
    {
        $userRepository = self::getContainer()->get(UserRepository::class);
        $admin = $userRepository->findOneBy(['email' => UserFixtures::ADMIN_EMAIL]);
        self::assertNotNull($admin, 'UserFixtures must seed the admin baseline.');
        self::assertSame(UserStatus::Approved, $admin->getStatus(), 'Fixture admin must start Approved so we can assert the block is rejected.');

        $this->loginAsApproved(UserFixtures::DOMAIN_MANAGER_EMAIL);

        // Grab a valid token from the list page — the voter denies before
        // the CSRF check would matter, but using a real token rules out a
        // false negative coming from token validation.
        $crawler = $this->client->request('GET', '/admin/users');
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/users/'.$admin->getId().'/block', [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);

        $reloaded = $userRepository->find($admin->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Approved, $reloaded->getStatus(), 'Block on an admin must be denied before any status flip.');
    }

    private function loginAsApproved(string $email): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        \assert(null !== $user, 'Test user must be created before login.');
        $this->client->loginUser($user);
    }
}
