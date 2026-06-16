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
 * `tests/bootstrap_integration.php` already calls `UserFixtures::load()`;
 * this test only re-invokes it so the lines land in the coverage report.
 * `UserManager` is `final` and can't be mocked directly, so we build a
 * real one with mocked collaborators.
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
