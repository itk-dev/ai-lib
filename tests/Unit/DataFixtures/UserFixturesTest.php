<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Unit-level coverage for {@see UserFixtures::load()}.
 *
 * The integration bootstrap (`tests/bootstrap_integration.php`) calls
 * `UserFixtures::load()` once before any test, but PHPUnit's coverage
 * collector only attributes execution that happens inside test methods.
 * This unit test invokes `load()` directly so the fixture's two
 * `createUser` calls land in coverage. `UserManager` is `final`, so we
 * instantiate the real one with mocked collaborators rather than
 * stubbing the manager itself.
 */
final class UserFixturesTest extends TestCase
{
    public function testLoadPersistsAliceAndBobWithFixturePassword(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $userRepository->method('findOneBy')->willReturn(null);
        $passwordHasher->method('hashPassword')->willReturn('hashed');

        $persistedEmails = [];
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedEmails): void {
                \assert($entity instanceof User);
                $persistedEmails[] = $entity->getEmail();
            });
        $entityManager->expects(self::exactly(2))->method('flush');

        $userManager = new UserManager($entityManager, $userRepository, $passwordHasher);
        $fixture = new UserFixtures($userManager);

        $fixture->load($this->createMock(ObjectManager::class));

        self::assertSame(['alice@example.test', 'bob@example.test'], $persistedEmails);
    }
}
