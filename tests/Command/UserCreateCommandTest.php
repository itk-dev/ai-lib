<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Security\UserManager;
use App\Tests\Support\ResetsDatabaseSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCreateCommandTest extends KernelTestCase
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
        $command = $application->find('app:user:create');
        $this->tester = new CommandTester($command);
    }

    public function testCreatesUser(): void
    {
        $exit = $this->tester->execute([
            'email' => 'alice@example.test',
            'password' => 'secret',
        ]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Created user "alice@example.test"', $this->tester->getDisplay());
    }

    public function testReportsFailureWhenEmailAlreadyExists(): void
    {
        $this->userManager->createUser('alice@example.test', 'first');

        $exit = $this->tester->execute([
            'email' => 'alice@example.test',
            'password' => 'second',
        ]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('already exists', $this->tester->getDisplay());
    }
}
