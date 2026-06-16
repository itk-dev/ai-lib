<?php

declare(strict_types=1);

namespace App\Tests\Integration\DataFixtures;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Asserts the baseline users from {@see \App\DataFixtures\UserFixtures}
 * are present after `tests/bootstrap_integration.php` has run.
 *
 * The bootstrap calls `UserFixtures::load()` once before any test
 * starts, so this test indirectly covers the fixture's wiring: if the
 * fixture or its `UserManager` dependency broke, the bootstrap would
 * have failed and no integration test would reach the assertions.
 */
final class UserFixturesTest extends KernelTestCase
{
    public function testBaselineContainsAliceAndBob(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(UserRepository::class);

        $alice = $repository->findOneBy(['email' => 'alice@example.test']);
        $bob = $repository->findOneBy(['email' => 'bob@example.test']);

        self::assertInstanceOf(User::class, $alice);
        self::assertInstanceOf(User::class, $bob);
    }
}
