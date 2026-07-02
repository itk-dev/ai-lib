<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\DataFixtures\UserFixtures;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\EmailConfirmation;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * End-to-end coverage of {@see EmailConfirmation}: issue a token,
 * consume it, and watch the user's status transition.
 *
 * Drives the service against the suite's real
 * `cache.email_confirmation` pool (the test environment swaps it
 * for an in-memory adapter, so tokens don't leak between tests)
 * and the real Doctrine wiring. The
 * {@see UserFixtures::AWAITING_EMAIL} row provides the
 * `AwaitingEmailConfirmation` user every test needs; DAMA rolls
 * back every per-test mutation, so deletes and status flips here
 * don't leak into later tests.
 */
final class EmailConfirmationTest extends KernelTestCase
{
    private EmailConfirmation $emailConfirmation;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->emailConfirmation = $container->get(EmailConfirmation::class);
        $this->userRepository = $container->get(UserRepository::class);
    }

    // Verifies issueToken returns an opaque base64url string of plausible length.
    public function testIssueTokenReturnsOpaqueRandomToken(): void
    {
        $token = $this->emailConfirmation->issueToken($this->awaitingFixtureUser());

        // 32 random bytes -> 43 base64url chars after padding strip.
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
    }

    // Tests the happy path: consume() flips an AwaitingEmailConfirmation user to Pending and returns them.
    public function testConsumeTransitionsAwaitingUserToPending(): void
    {
        $user = $this->awaitingFixtureUser();
        $token = $this->emailConfirmation->issueToken($user);

        $confirmed = $this->emailConfirmation->consume($token);

        self::assertNotNull($confirmed);
        self::assertSame((string) $user->getId(), (string) $confirmed->getId());

        $reloaded = $this->userRepository->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Pending, $reloaded->getStatus());
    }

    // Ensures the token is single-use: a second consume() of the same token returns null.
    public function testConsumeIsSingleUse(): void
    {
        $token = $this->emailConfirmation->issueToken($this->awaitingFixtureUser());

        $this->emailConfirmation->consume($token);
        $secondAttempt = $this->emailConfirmation->consume($token);

        self::assertNull($secondAttempt);
    }

    // Verifies an unknown token returns null without touching any user.
    public function testConsumeReturnsNullForUnknownToken(): void
    {
        $result = $this->emailConfirmation->consume('this-token-was-never-issued-abcdef');

        self::assertNull($result);
    }

    // Tests the defensive branch: consume() returns null when the cache row points at a user that has since been deleted from the database.
    public function testConsumeReturnsNullWhenUserHasBeenDeleted(): void
    {
        $user = $this->awaitingFixtureUser();
        $token = $this->emailConfirmation->issueToken($user);

        // Delete the user row but leave the cache token alone — the
        // service must surface null and not throw on the stale mapping.
        // DAMA rolls back the delete at tearDown, so the fixture
        // baseline is restored for the next test.
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->remove($user);
        $em->flush();

        $result = $this->emailConfirmation->consume($token);

        self::assertNull($result);
    }

    // Tests that consuming a token for a user whose status has already moved beyond AwaitingEmailConfirmation returns null and leaves the status untouched.
    public function testConsumeReturnsNullWhenStatusAlreadyAdvanced(): void
    {
        $user = $this->awaitingFixtureUser();
        $token = $this->emailConfirmation->issueToken($user);

        // Simulate the moderator approving before the user clicks
        // their confirmation link — the token must no longer flip
        // the status backwards.
        self::getContainer()->get(UserManager::class)->updateUser(
            UserFixtures::AWAITING_EMAIL,
            status: UserStatus::Approved,
        );

        $result = $this->emailConfirmation->consume($token);

        self::assertNull($result);
        $reloaded = $this->userRepository->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Approved, $reloaded->getStatus(), 'Status must not be downgraded by a stale token.');
    }

    private function awaitingFixtureUser(): \App\Entity\User
    {
        $user = $this->userRepository->findOneBy(['email' => UserFixtures::AWAITING_EMAIL]);
        \assert(null !== $user, 'UserFixtures must seed the AwaitingEmailConfirmation baseline.');
        self::assertSame(UserStatus::AwaitingEmailConfirmation, $user->getStatus());

        return $user;
    }
}
