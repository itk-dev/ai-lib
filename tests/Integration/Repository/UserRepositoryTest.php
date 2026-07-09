<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\Roles;
use App\Security\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Cover the `PasswordUpgraderInterface` hook on {@see UserRepository}.
 *
 * Symfony Security calls `upgradePassword()` automatically during
 * authentication when it detects a hash that needs rehashing (e.g.
 * the configured cost has increased). The functional login test does
 * not exercise that path because the fixtures already hash with the
 * current algorithm, so we cover the upgrade method directly here.
 *
 * Uses baseline alice from `UserFixtures`, loaded by
 * `tests/bootstrap_integration.php`.
 */
final class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->repository = $container->get(UserRepository::class);
    }

    // Tests that upgradePassword() writes the new hash on a fixture user and the change persists across reloads.
    public function testUpgradePasswordWritesTheNewHash(): void
    {
        $alice = $this->repository->findOneBy(['email' => 'alice@example.test']);
        self::assertNotNull($alice);
        $oldHash = $alice->getPassword();

        $this->repository->upgradePassword($alice, 'a-new-hash');

        self::assertSame('a-new-hash', $alice->getPassword());

        $reloaded = $this->repository->find($alice->getId());
        self::assertNotNull($reloaded);
        self::assertSame('a-new-hash', $reloaded->getPassword());
        self::assertNotSame($oldHash, $reloaded->getPassword());
    }

    // Ensures upgradePassword() throws UnsupportedUserException when handed a user not of the App\Entity\User class.
    public function testUpgradePasswordRejectsForeignUserType(): void
    {
        $foreignUser = new class () implements PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string
            {
                return null;
            }
        };

        $this->expectException(UnsupportedUserException::class);

        $this->repository->upgradePassword($foreignUser, 'irrelevant');
    }

    // Verifies the UserStatus enum mapping round-trips: persisted then reloaded keeps the same enum case.
    public function testStatusEnumRoundTripsThroughThePersistedRow(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail('eve@example.test')
            ->setName('Eve')
            ->setPassword('hash')
            ->setStatus(UserStatus::Blocked);
        $em->persist($user);
        $em->flush();
        $em->clear();

        $reloaded = $this->repository->find($user->getId());

        self::assertNotNull($reloaded);
        self::assertSame('Eve', $reloaded->getName());
        self::assertSame(UserStatus::Blocked, $reloaded->getStatus());
    }

    public function testFindVisibleToReturnsEveryUserForAdmin(): void
    {
        $manager = self::getContainer()->get(UserManager::class);
        $admin = $manager->createUser('admin@example.test', 'Admin', 'pw', [Roles::ADMIN]);
        $manager->createUser('eve@other.test', 'Eve', 'pw', status: UserStatus::Pending);

        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findVisibleTo($admin),
        );

        self::assertContains('alice@example.test', $emails);
        self::assertContains('bob@example.test', $emails);
        self::assertContains('admin@example.test', $emails);
        self::assertContains('eve@other.test', $emails);
    }

    public function testFindVisibleToScopesByDomainForDomainManager(): void
    {
        $manager = self::getContainer()->get(UserManager::class);
        $domainManager = $manager->createUser('dm@example.test', 'DM', 'pw', [Roles::DOMAIN_MANAGER]);
        $manager->createUser('outsider@other.test', 'Outsider', 'pw');

        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findVisibleTo($domainManager),
        );

        self::assertContains('alice@example.test', $emails);
        self::assertContains('bob@example.test', $emails);
        self::assertContains('dm@example.test', $emails);
        self::assertNotContains('outsider@other.test', $emails);

        // A plain authenticated user (no DOMAIN_MANAGER / ADMIN role) sees
        // no one — the repository falls through to an empty result.
        $alice = $this->repository->findOneBy(['email' => 'alice@example.test']);
        self::assertNotNull($alice);
        self::assertSame([], $this->repository->findVisibleTo($alice));

        // Defensive: a domain manager with no email also gets an empty
        // result rather than running a query against an unresolved domain.
        $headless = (new User())->setRoles([Roles::DOMAIN_MANAGER]);
        self::assertSame([], $this->repository->findVisibleTo($headless));
    }

    public function testFindVisibleToFiltersByStatus(): void
    {
        $manager = self::getContainer()->get(UserManager::class);
        // Admin must start Approved so it doesn't itself match the Pending filter
        // we're testing.
        $admin = $manager->createUser('siteadmin@example.test', 'Site Admin', 'pw', [Roles::ADMIN], status: UserStatus::Approved);
        $manager->createUser('pending@example.test', 'Pending', 'pw', status: UserStatus::Pending);

        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findVisibleTo($admin, UserStatus::Pending),
        );

        // The fixture seeds other pending users; assertion only pins the
        // test-created row and verifies non-pending users are filtered out.
        self::assertContains('pending@example.test', $emails);
        self::assertNotContains('siteadmin@example.test', $emails);
        self::assertNotContains('alice@example.test', $emails);
    }

    // Verifies findApprovedDomainManagersForDomain returns every ROLE_DOMAIN_MANAGER on the given domain.
    public function testFindApprovedDomainManagersForDomainReturnsSameDomainManagers(): void
    {
        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findApprovedDomainManagersForDomain('aarhus.dk'),
        );

        self::assertContains(UserFixtures::DOMAIN_MANAGER_EMAIL, $emails);
        self::assertContains(UserFixtures::SECOND_DOMAIN_MANAGER_EMAIL, $emails);
    }

    // Ensures site admins are excluded even when they share the target domain — they already receive the admin recipient's mail.
    public function testFindApprovedDomainManagersForDomainExcludesSiteAdmins(): void
    {
        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findApprovedDomainManagersForDomain('aarhus.dk'),
        );

        self::assertNotContains(UserFixtures::ADMIN_EMAIL, $emails);
    }

    // Ensures plain (non-manager) same-domain users are excluded.
    public function testFindApprovedDomainManagersForDomainExcludesPlainUsers(): void
    {
        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findApprovedDomainManagersForDomain('aarhus.dk'),
        );

        self::assertNotContains(UserFixtures::COLLEAGUE_EMAIL, $emails);
    }

    // Verifies managers on other domains are excluded.
    public function testFindApprovedDomainManagersForDomainScopesByDomain(): void
    {
        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findApprovedDomainManagersForDomain('aalborg.dk'),
        );

        self::assertNotContains(UserFixtures::DOMAIN_MANAGER_EMAIL, $emails);
        self::assertSame([], $emails);
    }

    // Ensures the domain match is case-insensitive so a caller passing a mixed-case domain still hits.
    public function testFindApprovedDomainManagersForDomainIsCaseInsensitive(): void
    {
        $lower = $this->repository->findApprovedDomainManagersForDomain('aarhus.dk');
        $upper = $this->repository->findApprovedDomainManagersForDomain('AARHUS.DK');

        self::assertSame(
            array_map(static fn (User $u): ?string => (string) $u->getId(), $lower),
            array_map(static fn (User $u): ?string => (string) $u->getId(), $upper),
        );
    }

    // Verifies non-Approved managers (Pending / Blocked / Awaiting) are filtered out — mailing them makes no sense; they can't act on the queue.
    public function testFindApprovedDomainManagersForDomainFiltersByStatus(): void
    {
        $manager = self::getContainer()->get(UserManager::class);
        $manager->createUser('pending-manager@aarhus.dk', 'PM', 'pw', [Roles::DOMAIN_MANAGER], UserStatus::Pending);
        $manager->createUser('blocked-manager@aarhus.dk', 'BM', 'pw', [Roles::DOMAIN_MANAGER], UserStatus::Blocked);
        $manager->createUser('awaiting-manager@aarhus.dk', 'AM', 'pw', [Roles::DOMAIN_MANAGER], UserStatus::AwaitingEmailConfirmation);

        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findApprovedDomainManagersForDomain('aarhus.dk'),
        );

        self::assertNotContains('pending-manager@aarhus.dk', $emails);
        self::assertNotContains('blocked-manager@aarhus.dk', $emails);
        self::assertNotContains('awaiting-manager@aarhus.dk', $emails);
    }
}
