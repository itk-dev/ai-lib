<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\EmailDomain;
use PHPUnit\Framework\TestCase;

final class EmailDomainTest extends TestCase
{
    public function testReturnsLowercasedDomain(): void
    {
        $user = new User();
        $user->setEmail('Alice@Aarhus.DK');

        self::assertSame('aarhus.dk', EmailDomain::of($user));
    }

    public function testReturnsNullForUserWithoutEmail(): void
    {
        self::assertNull(EmailDomain::of(new User()));
    }

    public function testReturnsNullWhenEmailHasNoAtSign(): void
    {
        $user = new User();
        $user->setEmail('not-an-email');

        self::assertNull(EmailDomain::of($user));
    }

    public function testReturnsNullWhenEmailEndsWithAtSign(): void
    {
        $user = new User();
        $user->setEmail('orphan@');

        self::assertNull(EmailDomain::of($user));
    }

    public function testHandlesSubaddressingByKeepingTheDomainOnly(): void
    {
        // "user+tag@domain" still has exactly one @; the helper splits on the rightmost.
        $user = new User();
        $user->setEmail('alice+sub@aarhus.dk');

        self::assertSame('aarhus.dk', EmailDomain::of($user));
    }
}
