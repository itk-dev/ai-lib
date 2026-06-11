<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Security\UserManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seed two baseline users for local development.
 *
 * `alice@example.test` and `bob@example.test` both get the same
 * intentionally weak password (`password`) so they're convenient to
 * paste into the login form. The fixture is excluded from prod by
 * Doctrine's standard fixtures workflow (`bin/console
 * doctrine:fixtures:load` is a dev/test command).
 */
final class UserFixtures extends Fixture
{
    /**
     * @param UserManager $userManager service that creates the persisted
     *                                 {@see \App\Entity\User} entities,
     *                                 keeping fixture code from duplicating
     *                                 the hashing wiring
     */
    public function __construct(private readonly UserManager $userManager)
    {
    }

    /**
     * Persist the two baseline users via {@see UserManager::createUser()}.
     *
     * @param ObjectManager $manager unused — the {@see UserManager}
     *                               flushes through its own injected
     *                               entity manager
     */
    public function load(ObjectManager $manager): void
    {
        $this->userManager->createUser('alice@example.test', 'password');
        $this->userManager->createUser('bob@example.test', 'password');
    }
}
