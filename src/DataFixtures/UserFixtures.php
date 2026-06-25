<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Enum\UserStatus;
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
     * E-mail of the first baseline user, shared with the fixtures that look
     * her up to assign as a creating user.
     */
    public const string ALICE_EMAIL = 'alice@example.test';

    /**
     * E-mail of the second baseline user, shared with the fixtures that look
     * him up to assign as a creating user.
     */
    public const string BOB_EMAIL = 'bob@example.test';

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
        $this->userManager->createUser(self::ALICE_EMAIL, 'Alice', 'password', status: UserStatus::Approved);
        $this->userManager->createUser(self::BOB_EMAIL, 'Bob', 'password', status: UserStatus::Approved);
    }
}
