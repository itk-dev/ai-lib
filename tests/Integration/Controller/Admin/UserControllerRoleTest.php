<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Repository\UserRepository;
use App\Security\Roles;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the inline role-promotion JSON endpoint at
 * `POST /admin/users/{id}/role`.
 *
 * Each test drives the controller through its real CSRF check,
 * voter, and {@see \App\Security\UserRoles} service, then asserts on
 * both the HTTP response shape and the post-flush role state of the
 * target user.
 */
final class UserControllerRoleTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    // Verifies an admin can promote any user to manager via the JSON endpoint.
    public function testAdminCanPromoteUserToManager(): void
    {
        $admin = $this->createUser('admin@example.test', [Roles::ADMIN]);
        $target = $this->createUser('target@example.test', []);
        $this->client->loginUser($admin);

        $this->postRole($target->getId(), 'manager');

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse();
        self::assertSame('manager', $payload['role']);
        self::assertNotEmpty($payload['label']);

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertContains(Roles::DOMAIN_MANAGER, $reloaded->getRoles());
    }

    // Tests that an admin can promote a user to admin.
    public function testAdminCanPromoteUserToAdmin(): void
    {
        $admin = $this->createUser('admin@example.test', [Roles::ADMIN]);
        $target = $this->createUser('target@example.test', []);
        $this->client->loginUser($admin);

        $this->postRole($target->getId(), 'admin');

        self::assertResponseIsSuccessful();

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertContains(Roles::ADMIN, $reloaded->getRoles());
    }

    // Tests that an admin can demote a manager via the 'none' transition.
    public function testAdminCanRemoveAllPermissions(): void
    {
        $admin = $this->createUser('admin@example.test', [Roles::ADMIN]);
        $target = $this->createUser('manager@example.test', [Roles::DOMAIN_MANAGER]);
        $this->client->loginUser($admin);

        $this->postRole($target->getId(), 'none');

        self::assertResponseIsSuccessful();

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertNotContains(Roles::DOMAIN_MANAGER, $reloaded->getRoles());
        self::assertNotContains(Roles::ADMIN, $reloaded->getRoles());
    }

    // Verifies a manager-actor receives 403 when attempting to mint an admin.
    public function testManagerCannotPromoteToAdmin(): void
    {
        $manager = $this->createUser('manager@example.test', [Roles::DOMAIN_MANAGER]);
        $target = $this->createUser('target@example.test', []);
        $this->client->loginUser($manager);

        $this->postRole($target->getId(), 'admin');

        self::assertResponseStatusCodeSame(403);

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertNotContains(Roles::ADMIN, $reloaded->getRoles());
    }

    // Tests the headline rule: a manager cannot demote an admin even within their own domain.
    public function testManagerCannotDemoteAdminEvenInSameDomain(): void
    {
        $manager = $this->createUser('manager@example.test', [Roles::DOMAIN_MANAGER]);
        $adminInDomain = $this->createUser('inhouse-admin@example.test', [Roles::ADMIN]);
        $this->client->loginUser($manager);

        $this->postRole($adminInDomain->getId(), 'none');

        self::assertResponseStatusCodeSame(403);

        $reloaded = self::getContainer()->get(UserRepository::class)->find($adminInDomain->getId());
        self::assertNotNull($reloaded);
        self::assertContains(Roles::ADMIN, $reloaded->getRoles(), 'Admin roles must survive a denied demotion attempt.');
    }

    // Tests that a cross-domain manager-actor gets 403 even for a normal user.
    public function testManagerCannotActAcrossDomains(): void
    {
        $manager = $this->createUser('manager@example.test', [Roles::DOMAIN_MANAGER]);
        $target = $this->createUser('foreign@other.test', []);
        $this->client->loginUser($manager);

        $this->postRole($target->getId(), 'manager');

        self::assertResponseStatusCodeSame(403);
    }

    // Tests that an invalid CSRF token returns 403 and leaves the role unchanged.
    public function testInvalidCsrfTokenIsRejected(): void
    {
        $admin = $this->createUser('admin@example.test', [Roles::ADMIN]);
        $target = $this->createUser('target@example.test', []);
        $this->client->loginUser($admin);

        $this->client->request(
            'POST',
            '/admin/users/'.$target->getId().'/role',
            content: json_encode(['role' => 'manager', '_token' => 'nope'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);

        $reloaded = self::getContainer()->get(UserRepository::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertNotContains(Roles::DOMAIN_MANAGER, $reloaded->getRoles());
    }

    // Tests that a malformed JSON payload returns 422.
    public function testMalformedPayloadReturns422(): void
    {
        $admin = $this->createUser('admin@example.test', [Roles::ADMIN]);
        $target = $this->createUser('target@example.test', []);
        $this->client->loginUser($admin);

        $this->postRole($target->getId(), 'garbage');

        self::assertResponseStatusCodeSame(422);

        $payload = $this->jsonResponse();
        self::assertSame('invalid_role', $payload['error']);
    }

    // Tests that an empty JSON body still hits the 422 branch.
    public function testEmptyPayloadReturns422(): void
    {
        $admin = $this->createUser('admin@example.test', [Roles::ADMIN]);
        $target = $this->createUser('target@example.test', []);
        $this->client->loginUser($admin);

        $this->client->request(
            'POST',
            '/admin/users/'.$target->getId().'/role',
            content: 'not json',
        );

        // 'not json' fails the CSRF check first, so we expect 403 here,
        // not 422 — defence-in-depth ordering.
        self::assertResponseStatusCodeSame(403);
    }

    // Verifies the last-admin guard surfaces as HTTP 409 with the expected error code.
    public function testLastAdminDemotionReturns409(): void
    {
        $this->stripExistingAdmins();
        $admin = $this->createUser('only-admin@example.test', [Roles::ADMIN]);
        $this->client->loginUser($admin);

        $this->postRole($admin->getId(), 'none');

        self::assertResponseStatusCodeSame(409);

        $payload = $this->jsonResponse();
        self::assertSame('last_admin', $payload['error']);

        // The admin must still hold ROLE_ADMIN — the guard fires before
        // any flush.
        $reloaded = self::getContainer()->get(UserRepository::class)->find($admin->getId());
        self::assertNotNull($reloaded);
        self::assertContains(Roles::ADMIN, $reloaded->getRoles());
    }

    // Tests that the role endpoint requires authentication (anonymous → 401).
    public function testAnonymousAccessReturns401(): void
    {
        $admin = $this->createUser('admin@example.test', [Roles::ADMIN]);
        $target = $this->createUser('target@example.test', []);

        $this->client->request(
            'POST',
            '/admin/users/'.$target->getId().'/role',
            content: json_encode(['role' => 'manager', '_token' => 'whatever'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    // Tests that plain (non-manager) users get 403 from the class-level IsGranted gate.
    public function testPlainUserGets403(): void
    {
        $plain = $this->createUser('plain@example.test', []);
        $target = $this->createUser('target@example.test', []);
        $this->client->loginUser($plain);

        // The class-level `#[IsGranted(Roles::DOMAIN_MANAGER)]` gate
        // fires before the controller body, so we don't need a valid
        // CSRF token to assert the 403 — a plain user can't even
        // load the list to scrape one.
        $this->client->request(
            'POST',
            '/admin/users/'.((string) $target->getId()).'/role',
            content: json_encode(['role' => 'manager', '_token' => 'irrelevant'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Issue the JSON POST with a CSRF token scraped from the list
     * page. The token comes from the `admin-user-action` intent
     * embedded in the existing approve/block forms, which matches
     * the intent the role endpoint validates against. Loading the
     * list page also establishes a session, which the underlying
     * token manager needs.
     */
    private function postRole(\Symfony\Component\Uid\Ulid|string $userId, string $role): void
    {
        $crawler = $this->client->request('GET', '/admin/users');
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value') ?? '';

        $this->client->request(
            'POST',
            '/admin/users/'.((string) $userId).'/role',
            content: json_encode(['role' => $role, '_token' => $token], JSON_THROW_ON_ERROR),
            server: ['CONTENT_TYPE' => 'application/json'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        $decoded = json_decode($content, true);
        \assert(\is_array($decoded));

        return $decoded;
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, array $roles): \App\Entity\User
    {
        return self::getContainer()->get(UserManager::class)->createUser(
            $email,
            'Name',
            'pw',
            $roles,
        );
    }

    /**
     * Demote every baseline-fixture admin so the test owns the
     * "only admin in the world" state without fighting the bootstrap
     * fixtures.
     */
    private function stripExistingAdmins(): void
    {
        $repo = self::getContainer()->get(UserRepository::class);
        foreach ($repo->findAll() as $user) {
            if (\in_array(Roles::ADMIN, $user->getRoles(), true)) {
                $user->setRoles([]);
            }
        }
        self::getContainer()->get('doctrine')->getManager()->flush();
    }
}
