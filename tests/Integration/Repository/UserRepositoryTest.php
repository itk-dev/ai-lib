<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
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

    // Tests that upgradePassword persists the new hash and a reload reflects it.
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

    // Ensures upgradePassword raises UnsupportedUserException for non-App User implementations.
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
}
