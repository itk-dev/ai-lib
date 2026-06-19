<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\AllowedEmailDomains;
use App\Security\Registration;
use App\Security\RegistrationException;
use App\Security\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationTest extends TestCase
{
    // Ensures malformed emails are rejected with the invalid_email translation key.
    public function testRejectsInvalidEmail(): void
    {
        $reg = $this->registration(allowList: 'example.test');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.invalid_email');

        $reg->register('not-an-email', 'Carol', 'secret', 'secret');
    }

    // Ensures emails outside the allow-list raise domain_not_allowed.
    public function testRejectsDomainNotOnAllowList(): void
    {
        $reg = $this->registration(allowList: 'aarhus.dk');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.domain_not_allowed');

        $reg->register('carol@example.test', 'Carol', 'secret', 'secret');
    }

    // Ensures mismatched password + confirmation raise password_mismatch.
    public function testRejectsPasswordMismatch(): void
    {
        $reg = $this->registration(allowList: 'example.test');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.password_mismatch');

        $reg->register('carol@example.test', 'Carol', 'secret', 'different');
    }

    // Ensures whitespace-only names raise empty_name.
    public function testRejectsEmptyName(): void
    {
        $reg = $this->registration(allowList: 'example.test');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.empty_name');

        $reg->register('carol@example.test', '   ', 'secret', 'secret');
    }

    // Ensures empty passwords raise empty_password.
    public function testRejectsEmptyPassword(): void
    {
        $reg = $this->registration(allowList: 'example.test');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.empty_password');

        $reg->register('carol@example.test', 'Carol', '', '');
    }

    // Verifies UserManager's duplicate-email DomainException is translated into a localised RegistrationException.
    public function testTranslatesDuplicateEmailIntoRegistrationException(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        // First call (in `register`) finds an existing user; UserManager throws DomainException.
        $repo->method('findOneBy')->willReturn(new User());

        $reg = new Registration(
            new UserManager($em, $repo, $hasher),
            new AllowedEmailDomains('example.test'),
        );

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.email_in_use');

        $reg->register('carol@example.test', 'Carol', 'secret', 'secret');
    }

    // Tests the happy path: valid submission persists a Pending user with trimmed name and hashed password.
    public function testPersistsPendingUserOnHappyPath(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $repo->method('findOneBy')->willReturn(null);
        $hasher->method('hashPassword')->willReturn('hashed-secret');

        $captured = null;
        $em->expects(self::once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$captured): void {
                \assert($entity instanceof User);
                $captured = $entity;
            });
        $em->expects(self::once())->method('flush');

        $reg = new Registration(
            new UserManager($em, $repo, $hasher),
            new AllowedEmailDomains('example.test'),
        );

        $user = $reg->register('Carol@Example.test', '  Carol  ', 'secret', 'secret');

        self::assertSame($user, $captured);
        self::assertSame('Carol@Example.test', $user->getEmail());
        self::assertSame('Carol', $user->getName(), 'Name is trimmed before persistence.');
        self::assertSame(UserStatus::Pending, $user->getStatus());
        self::assertSame('hashed-secret', $user->getPassword());
    }

    /**
     * Build a Registration with mock collaborators that never reach
     * the persistence step.
     */
    private function registration(string $allowList): Registration
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        return new Registration(
            new UserManager($em, $repo, $hasher),
            new AllowedEmailDomains($allowList),
        );
    }
}
