<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\AllowedEmailDomains;
use App\Security\RateLimitedRegistrationException;
use App\Security\Registration;
use App\Security\RegistrationException;
use App\Security\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class RegistrationTest extends TestCase
{
    private const string CLIENT_IP = '203.0.113.7';

    // Ensures malformed emails are rejected with the invalid_email translation key.
    public function testRejectsInvalidEmail(): void
    {
        $reg = $this->registration(allowList: 'example.test');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.invalid_email');

        $reg->register(self::CLIENT_IP, 'not-an-email', 'Carol', 'secret', 'secret');
    }

    // Ensures emails outside the allow-list raise domain_not_allowed.
    public function testRejectsDomainNotOnAllowList(): void
    {
        $reg = $this->registration(allowList: 'aarhus.dk');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.domain_not_allowed');

        $reg->register(self::CLIENT_IP, 'carol@example.test', 'Carol', 'secret', 'secret');
    }

    // Ensures mismatched password + confirmation raise password_mismatch.
    public function testRejectsPasswordMismatch(): void
    {
        $reg = $this->registration(allowList: 'example.test');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.password_mismatch');

        $reg->register(self::CLIENT_IP, 'carol@example.test', 'Carol', 'secret', 'different');
    }

    // Ensures whitespace-only names raise empty_name.
    public function testRejectsEmptyName(): void
    {
        $reg = $this->registration(allowList: 'example.test');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.empty_name');

        $reg->register(self::CLIENT_IP, 'carol@example.test', '   ', 'secret', 'secret');
    }

    // Ensures empty passwords raise empty_password.
    public function testRejectsEmptyPassword(): void
    {
        $reg = $this->registration(allowList: 'example.test');

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('register.error.empty_password');

        $reg->register(self::CLIENT_IP, 'carol@example.test', 'Carol', '', '');
    }

    // Verifies a duplicate-email submission is idempotent: returns null without throwing, never reaches persist().
    public function testDuplicateEmailReturnsNullWithoutPersisting(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        // UserManager's duplicate-email path returns the existing user
        // from the repository on the pre-flight findOneBy lookup.
        $repo->method('findOneBy')->willReturn(new User());

        // Idempotent path must NOT persist or flush.
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $reg = new Registration(
            new UserManager($em, $repo, $hasher),
            new AllowedEmailDomains('example.test'),
            $this->openLimiter(),
            $this->openLimiter(),
        );

        $result = $reg->register(self::CLIENT_IP, 'carol@example.test', 'Carol', 'secret', 'secret');

        self::assertNull($result);
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
            $this->openLimiter(),
            $this->openLimiter(),
        );

        $user = $reg->register(self::CLIENT_IP, 'Carol@Example.test', '  Carol  ', 'secret', 'secret');

        self::assertNotNull($user);
        self::assertSame($user, $captured);
        self::assertSame('Carol@Example.test', $user->getEmail());
        self::assertSame('Carol', $user->getName(), 'Name is trimmed before persistence.');
        self::assertSame(UserStatus::Pending, $user->getStatus());
        self::assertSame('hashed-secret', $user->getPassword());
    }

    // Verifies the per-IP limiter rejects the request before any validation runs.
    public function testRejectsWhenPerIpLimitExhausted(): void
    {
        $reg = new Registration(
            $this->buildUserManager(),
            new AllowedEmailDomains('example.test'),
            $this->closedLimiter(),
            $this->openLimiter(),
        );

        $this->expectException(RateLimitedRegistrationException::class);
        $this->expectExceptionMessage('register.error.rate_limited');

        $reg->register(self::CLIENT_IP, 'carol@example.test', 'Carol', 'secret', 'secret');
    }

    // Verifies the system-wide limiter rejects even when the per-IP limiter still has room.
    public function testRejectsWhenSystemWideLimitExhausted(): void
    {
        $reg = new Registration(
            $this->buildUserManager(),
            new AllowedEmailDomains('example.test'),
            $this->openLimiter(),
            $this->closedLimiter(),
        );

        $this->expectException(RateLimitedRegistrationException::class);
        $this->expectExceptionMessage('register.error.rate_limited');

        $reg->register(self::CLIENT_IP, 'carol@example.test', 'Carol', 'secret', 'secret');
    }

    /**
     * Build a Registration with mock collaborators that never reach
     * the persistence step. Limiters are no-op so the validation
     * branches under test always run.
     */
    private function registration(string $allowList): Registration
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        return new Registration(
            new UserManager($em, $repo, $hasher),
            new AllowedEmailDomains($allowList),
            $this->openLimiter(),
            $this->openLimiter(),
        );
    }

    /**
     * Build a real `UserManager` with mocked collaborators so the
     * rate-limit guard can short-circuit before persistence runs.
     */
    private function buildUserManager(): UserManager
    {
        return new UserManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserRepository::class),
            $this->createMock(UserPasswordHasherInterface::class),
        );
    }

    /**
     * Build a `RateLimiterFactoryInterface` that always accepts —
     * Symfony ships {@see NoLimiter} for exactly this case.
     */
    private function openLimiter(): RateLimiterFactoryInterface
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn(new NoLimiter());

        return $factory;
    }

    /**
     * Build a `RateLimiterFactoryInterface` whose `consume()` is
     * always rejected, so the registration rate-limit guard trips.
     */
    private function closedLimiter(): RateLimiterFactoryInterface
    {
        $rejected = new RateLimit(0, new \DateTimeImmutable('+1 hour'), false, 1);
        $limiter = $this->createMock(\Symfony\Component\RateLimiter\LimiterInterface::class);
        $limiter->method('consume')->willReturn($rejected);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return $factory;
    }
}
