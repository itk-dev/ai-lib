<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Enum\UserStatus;
use App\Security\UserApproval;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserApprovalTest extends KernelTestCase
{
    private UserManager $userManager;
    private UserApproval $userApproval;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->userManager = $container->get(UserManager::class);
        $this->userApproval = $container->get(UserApproval::class);
    }

    public function testApproveTransitionsPendingUserToApproved(): void
    {
        $user = $this->userManager->createUser(
            'kim@example.test',
            'Kim',
            'pw',
            status: UserStatus::Pending,
        );

        $this->userApproval->approve($user);

        self::assertSame(UserStatus::Approved, $user->getStatus());
    }

    public function testBlockTransitionsApprovedUserToBlocked(): void
    {
        $user = $this->userManager->createUser(
            'lara@example.test',
            'Lara',
            'pw',
        );

        $this->userApproval->block($user);

        self::assertSame(UserStatus::Blocked, $user->getStatus());
    }

    public function testApprovalIsRoundTrippedThroughTheDatabase(): void
    {
        $user = $this->userManager->createUser(
            'mona@example.test',
            'Mona',
            'pw',
            status: UserStatus::Pending,
        );
        $id = $user->getId();

        $this->userApproval->approve($user);

        self::bootKernel();
        $reloaded = self::getContainer()
            ->get(\App\Repository\UserRepository::class)
            ->find($id);

        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Approved, $reloaded->getStatus());
    }
}
