<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Security\UserManager;
use App\Tests\Support\ResetsDatabaseSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class UserChangePasswordCommandTest extends KernelTestCase
{
    use ResetsDatabaseSchemaTrait;

    private CommandTester $tester;
    private UserManager $userManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        self::resetSchema($container->get(EntityManagerInterface::class));

        $this->userManager = $container->get(UserManager::class);

        $application = new Application(self::$kernel);
        $command = $application->find('app:user:change-password');
        $this->tester = new CommandTester($command);
    }

    public function testChangesPassword(): void
    {
        $this->userManager->createUser('alice@example.test', 'old');

        $exit = $this->tester->execute([
            'email' => 'alice@example.test',
            'password' => 'new',
        ]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Updated password for user "alice@example.test"', $this->tester->getDisplay());
    }

    public function testReportsFailureWhenUserMissing(): void
    {
        $exit = $this->tester->execute([
            'email' => 'nobody@example.test',
            'password' => 'new',
        ]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('No user with the e-mail "nobody@example.test"', $this->tester->getDisplay());
    }
}
