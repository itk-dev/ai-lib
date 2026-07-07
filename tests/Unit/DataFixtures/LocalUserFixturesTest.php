<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\LocalUserFixtures;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\Roles;
use App\Security\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Unit coverage of {@see LocalUserFixtures}.
 *
 * The fixture belongs to the `local` group, so the integration
 * bootstrap does NOT run it — cover the two paths (group
 * declaration + persist calls) here instead so the 100 %
 * coverage gate stays intact. `UserManager` is final and can't
 * be doubled directly, so build a real one with mocked
 * collaborators — same pattern as {@see UserFixturesTest}.
 */
final class LocalUserFixturesTest extends TestCase
{
    // Ensures the fixture is scoped to the `local` group so `doctrine:fixtures:load` (no --group) never picks it up alongside the general set.
    public function testBelongsToLocalGroupOnly(): void
    {
        self::assertSame(['local'], LocalUserFixtures::getGroups());
    }

    // Verifies load() creates the two personal-inbox admin accounts through UserManager with the expected role + status.
    public function testLoadCreatesTheTwoAdminAccounts(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $userRepository->method('findOneBy')->willReturn(null);
        $passwordHasher->method('hashPassword')->willReturn('hashed');

        $persisted = [];
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                \assert($entity instanceof User);
                $persisted[] = $entity;
            });
        $entityManager->expects(self::exactly(2))->method('flush');

        $userManager = new UserManager($entityManager, $userRepository, $passwordHasher);
        (new LocalUserFixtures($userManager))->load($this->createMock(ObjectManager::class));

        $emails = array_map(static fn (User $u): string => (string) $u->getEmail(), $persisted);
        self::assertSame(['my@aarhus.dk', 'lilosti@aarhus.dk'], $emails);

        foreach ($persisted as $user) {
            self::assertContains(Roles::ADMIN, $user->getRoles(), 'both seeded accounts are site admins');
            self::assertSame(UserStatus::Approved, $user->getStatus(), 'both seeded accounts land approved');
        }
    }
}
