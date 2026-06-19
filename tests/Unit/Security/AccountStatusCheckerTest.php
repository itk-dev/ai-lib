<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Security\AccountStatusChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

final class AccountStatusCheckerTest extends TestCase
{
    public function testApprovedUserPassesPreAuth(): void
    {
        $user = (new User())
            ->setName('Alice')
            ->setStatus(UserStatus::Approved);

        (new AccountStatusChecker())->checkPreAuth($user);

        // No exception thrown is the assertion; explicit to keep PHPUnit happy.
        self::assertTrue(true);
    }

    public function testPendingUserIsRejectedWithLocalisedMessage(): void
    {
        $user = (new User())
            ->setName('Pending')
            ->setStatus(UserStatus::Pending);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('account.pending');

        (new AccountStatusChecker())->checkPreAuth($user);
    }

    public function testBlockedUserIsRejectedWithLocalisedMessage(): void
    {
        $user = (new User())
            ->setName('Blocked')
            ->setStatus(UserStatus::Blocked);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('account.blocked');

        (new AccountStatusChecker())->checkPreAuth($user);
    }

    public function testForeignUserImplementationsAreIgnored(): void
    {
        $foreignUser = $this->createMock(UserInterface::class);

        (new AccountStatusChecker())->checkPreAuth($foreignUser);

        self::assertTrue(true);
    }

    public function testCheckPostAuthIsANoOp(): void
    {
        $user = (new User())->setStatus(UserStatus::Approved);

        (new AccountStatusChecker())->checkPostAuth($user);

        self::assertTrue(true);
    }
}
