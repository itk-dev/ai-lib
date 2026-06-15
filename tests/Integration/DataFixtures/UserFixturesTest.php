<?php

declare(strict_types=1);

namespace App\Tests\Integration\DataFixtures;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies the local-dev fixture loads both baseline users.
 *
 * The fixture itself is straight-line code, but exercising it through
 * a test keeps it in coverage and catches regressions in the
 * {@see \App\Security\UserManager} wiring it depends on.
 */
final class UserFixturesTest extends KernelTestCase
{
    public function testLoadsAliceAndBob(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $container->get(UserFixtures::class)->load($em);

        $repository = $container->get(UserRepository::class);
        $alice = $repository->findOneBy(['email' => 'alice@example.test']);
        $bob = $repository->findOneBy(['email' => 'bob@example.test']);

        self::assertInstanceOf(User::class, $alice);
        self::assertInstanceOf(User::class, $bob);
    }
}
