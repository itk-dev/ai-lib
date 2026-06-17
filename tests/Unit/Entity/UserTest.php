<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();

        self::assertSame(['ROLE_USER'], $user->getRoles());

        $user->setRoles(['ROLE_ADMIN']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesDeduplicatesRoleUserWhenAlreadyPresent(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
    }

    public function testGetUserIdentifierReturnsEmptyStringWhenEmailIsNull(): void
    {
        $user = new User();

        self::assertSame('', $user->getUserIdentifier());
    }

    public function testGetUserIdentifierReturnsEmailWhenSet(): void
    {
        $user = new User();
        $user->setEmail('alice@example.test');

        self::assertSame('alice@example.test', $user->getUserIdentifier());
    }

    public function testSerializeReplacesPasswordWithCrc32cHash(): void
    {
        $user = new User();
        $user->setEmail('alice@example.test');
        $user->setPassword('plaintext-hash');

        $data = $user->__serialize();

        $passwordKey = "\0".User::class."\0password";
        self::assertArrayHasKey($passwordKey, $data);
        self::assertSame(hash('crc32c', 'plaintext-hash'), $data[$passwordKey]);
        self::assertNotContains('plaintext-hash', $data, 'Serialised payload must not contain the original password hash.');
    }

    public function testSettersMutateAndReturnStatic(): void
    {
        $user = new User();

        self::assertSame($user, $user->setEmail('bob@example.test'));
        self::assertSame('bob@example.test', $user->getEmail());

        self::assertSame($user, $user->setPassword('hashed-password'));
        self::assertSame('hashed-password', $user->getPassword());

        self::assertSame($user, $user->setRoles(['ROLE_EDITOR']));
        self::assertSame(['ROLE_EDITOR', 'ROLE_USER'], $user->getRoles());

        self::assertNull($user->getId());
    }
}
