<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Security\UserManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seed two baseline users for local development.
 *
 * `alice@example.test` and `bob@example.test`, both with the
 * intentionally-weak password `password`, so they're easy to paste
 * into the login form.
 */
final class UserFixtures extends Fixture
{
    /**
     * @param UserManager $userManager service that creates the persisted users
     */
    public function __construct(private readonly UserManager $userManager)
    {
    }

    /**
     * Persist the two baseline users via {@see UserManager::createUser()}.
     *
     * @param ObjectManager $manager unused — UserManager flushes its own entity manager
     */
    public function load(ObjectManager $manager): void
    {
        $this->userManager->createUser('alice@example.test', 'password');
        $this->userManager->createUser('bob@example.test', 'password');
    }
}
