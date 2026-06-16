<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\UserRepository;
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
        $foreignUser = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string
            {
                return null;
            }
        };

        $this->expectException(UnsupportedUserException::class);

        $this->repository->upgradePassword($foreignUser, 'irrelevant');
    }
}
