<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Repository\UserRepository;
use App\Security\UserManager;
use App\Tests\Support\ResetsDatabaseSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserManagerTest extends KernelTestCase
{
    use ResetsDatabaseSchemaTrait;

    private UserManager $userManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::resetSchema($container->get(EntityManagerInterface::class));

        $this->userManager = $container->get(UserManager::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testCreatesAndPersistsUserWithHashedPassword(): void
    {
        $user = $this->userManager->createUser('alice@example.test', 'secret');

        self::assertNotNull($user->getId());
        self::assertSame('alice@example.test', $user->getEmail());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNotSame('secret', $user->getPassword(), 'Password must be hashed.');
        self::assertTrue(
            $this->passwordHasher->isPasswordValid($user, 'secret'),
            'Hashed password must verify against the original plain text.',
        );
        self::assertSame($user->getId(), $this->userRepository->findOneBy(['email' => 'alice@example.test'])?->getId());
    }

    public function testCreateUserStoresExtraRoles(): void
    {
        $user = $this->userManager->createUser('admin@example.test', 'secret', ['ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testCreateUserRejectsDuplicateEmail(): void
    {
        $this->userManager->createUser('alice@example.test', 'secret');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('alice@example.test');

        $this->userManager->createUser('alice@example.test', 'other');
    }

    public function testCreateUserRejectsEmptyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must not be empty.');

        $this->userManager->createUser('alice@example.test', '');
    }

    public function testChangePasswordReplacesTheHash(): void
    {
        $user = $this->userManager->createUser('alice@example.test', 'old');
        $oldHash = $user->getPassword();

        $updated = $this->userManager->changePassword('alice@example.test', 'new');

        self::assertSame($user->getId(), $updated->getId());
        self::assertNotSame($oldHash, $updated->getPassword());
        self::assertTrue($this->passwordHasher->isPasswordValid($updated, 'new'));
        self::assertFalse($this->passwordHasher->isPasswordValid($updated, 'old'));
    }

    public function testChangePasswordFailsWhenUserMissing(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('nobody@example.test');

        $this->userManager->changePassword('nobody@example.test', 'whatever');
    }

    public function testChangePasswordRejectsEmptyPassword(): void
    {
        $this->userManager->createUser('alice@example.test', 'secret');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must not be empty.');

        $this->userManager->changePassword('alice@example.test', '');
    }
}
