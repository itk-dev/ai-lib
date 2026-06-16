<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Repository\UserRepository;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests for {@see UserManager}.
 *
 * Relies on the baseline `UserFixtures` (alice + bob with password
 * `password`) loaded by `tests/bootstrap_integration.php`. Tests that
 * exercise the "create a brand-new user" path use a non-fixture email
 * (`charlie@example.test`) to avoid colliding with the baseline.
 */
final class UserManagerTest extends KernelTestCase
{
    private UserManager $userManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->userManager = $container->get(UserManager::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testCreatesAndPersistsUserWithHashedPassword(): void
    {
        $user = $this->userManager->createUser('charlie@example.test', 'secret');

        self::assertNotNull($user->getId());
        self::assertSame('charlie@example.test', $user->getEmail());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNotSame('secret', $user->getPassword(), 'Password must be hashed.');
        self::assertTrue(
            $this->passwordHasher->isPasswordValid($user, 'secret'),
            'Hashed password must verify against the original plain text.',
        );
        self::assertSame($user->getId(), $this->userRepository->findOneBy(['email' => 'charlie@example.test'])?->getId());
    }

    public function testCreateUserStoresExtraRoles(): void
    {
        $user = $this->userManager->createUser('admin@example.test', 'secret', ['ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testCreateUserRejectsDuplicateEmail(): void
    {
        // alice@example.test is loaded by UserFixtures in the bootstrap.
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('alice@example.test');

        $this->userManager->createUser('alice@example.test', 'other');
    }

    public function testCreateUserRejectsEmptyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must not be empty.');

        $this->userManager->createUser('charlie@example.test', '');
    }

    public function testChangePasswordReplacesTheHash(): void
    {
        $alice = $this->userRepository->findOneBy(['email' => 'alice@example.test']);
        self::assertNotNull($alice);
        $oldHash = $alice->getPassword();

        $updated = $this->userManager->changePassword('alice@example.test', 'new');

        self::assertSame($alice->getId(), $updated->getId());
        self::assertNotSame($oldHash, $updated->getPassword());
        self::assertTrue($this->passwordHasher->isPasswordValid($updated, 'new'));
        self::assertFalse($this->passwordHasher->isPasswordValid($updated, 'password'));
    }

    public function testChangePasswordFailsWhenUserMissing(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('nobody@example.test');

        $this->userManager->changePassword('nobody@example.test', 'whatever');
    }

    public function testChangePasswordRejectsEmptyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must not be empty.');

        $this->userManager->changePassword('alice@example.test', '');
    }
}
