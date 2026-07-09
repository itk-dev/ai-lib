<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\Roles;
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
        $admin = $this->repository->findOneBy(['email' => UserFixtures::ADMIN_EMAIL]);
        self::assertNotNull($admin);

        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findVisibleTo($admin),
        );

        // Admin lives on aarhus.dk but sees every fixture user across every domain.
        self::assertContains(UserFixtures::ALICE_EMAIL, $emails);
        self::assertContains(UserFixtures::BOB_EMAIL, $emails);
        self::assertContains(UserFixtures::ADMIN_EMAIL, $emails);
        self::assertContains(UserFixtures::PENDING_EMAIL, $emails);
        self::assertContains(UserFixtures::BLOCKED_EMAIL, $emails);
    }

    public function testFindVisibleToScopesByDomainForDomainManager(): void
    {
        $domainManager = $this->repository->findOneBy(['email' => UserFixtures::DOMAIN_MANAGER_EMAIL]);
        self::assertNotNull($domainManager);

        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findVisibleTo($domainManager),
        );

        // DOMAIN_MANAGER_EMAIL lives on aarhus.dk — sees the same-domain admin
        // and colleague, does not see users on aalborg.dk / odense.dk / example.test.
        self::assertContains(UserFixtures::ADMIN_EMAIL, $emails);
        self::assertContains(UserFixtures::COLLEAGUE_EMAIL, $emails);
        self::assertNotContains(UserFixtures::PENDING_EMAIL, $emails);
        self::assertNotContains(UserFixtures::BLOCKED_EMAIL, $emails);
        self::assertNotContains(UserFixtures::ALICE_EMAIL, $emails);

        // A plain authenticated user (no DOMAIN_MANAGER / ADMIN role) sees
        // no one — the repository falls through to an empty result.
        $alice = $this->repository->findOneBy(['email' => UserFixtures::ALICE_EMAIL]);
        self::assertNotNull($alice);
        self::assertSame([], $this->repository->findVisibleTo($alice));

        // Defensive: a domain manager with no email also gets an empty
        // result rather than running a query against an unresolved domain.
        $headless = (new User())->setRoles([Roles::DOMAIN_MANAGER]);
        self::assertSame([], $this->repository->findVisibleTo($headless));
    }

    public function testFindVisibleToFiltersByStatus(): void
    {
        $admin = $this->repository->findOneBy(['email' => UserFixtures::ADMIN_EMAIL]);
        self::assertNotNull($admin);

        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findVisibleTo($admin, UserStatus::Pending),
        );

        // Only the Pending fixture user matches; every non-Pending fixture user is filtered out.
        self::assertContains(UserFixtures::PENDING_EMAIL, $emails);
        self::assertNotContains(UserFixtures::ADMIN_EMAIL, $emails);
        self::assertNotContains(UserFixtures::ALICE_EMAIL, $emails);
    }
}
