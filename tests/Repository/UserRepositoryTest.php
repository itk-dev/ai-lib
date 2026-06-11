<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\UserRepository;
use App\Security\UserManager;
use App\Tests\Support\ResetsDatabaseSchemaTrait;
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
 */
final class UserRepositoryTest extends KernelTestCase
{
    use ResetsDatabaseSchemaTrait;

    private UserRepository $repository;
    private UserManager $userManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        self::resetSchema($container->get(EntityManagerInterface::class));

        $this->repository = $container->get(UserRepository::class);
        $this->userManager = $container->get(UserManager::class);
    }

    public function testUpgradePasswordWritesTheNewHash(): void
    {
        $user = $this->userManager->createUser('alice@example.test', 'old');
        $oldHash = $user->getPassword();

        $this->repository->upgradePassword($user, 'a-new-hash');

        self::assertSame('a-new-hash', $user->getPassword());

        $reloaded = $this->repository->find($user->getId());
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
