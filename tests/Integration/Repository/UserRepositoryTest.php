<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

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
        $admin = $manager->createUser('siteadmin@example.test', 'Site Admin', 'pw', [Roles::ADMIN]);
        $manager->createUser('pending@example.test', 'Pending', 'pw', status: UserStatus::Pending);

        $emails = array_map(
            static fn (User $u): ?string => $u->getEmail(),
            $this->repository->findVisibleTo($admin, UserStatus::Pending),
        );

        self::assertSame(['pending@example.test'], $emails);
    }
}
