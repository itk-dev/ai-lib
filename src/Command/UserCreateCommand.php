<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command that creates a new application user.
 *
 * Usage: `task console -- app:user:create <email> <password>`.
 * Delegates to {@see UserManager::createUser()} for the actual
 * persistence and hashing — the command itself just adapts CLI input
 * and renders the outcome.
 */
#[AsCommand(
    name: 'app:user:create',
    description: 'Create a new application user with a hashed password.',
)]
final class UserCreateCommand extends Command
{
    /**
     * @param UserManager $userManager service that owns user creation,
     *                                 password hashing, and persistence
     */
    public function __construct(private readonly UserManager $userManager)
    {
        parent::__construct();
    }

    /**
     * Declare CLI arguments.
     */
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The user\'s e-mail address (must be unique).')
            ->addArgument('password', InputArgument::REQUIRED, 'The user\'s password in clear-text — will be hashed.');
    }

    /**
     * Adapt console arguments to the {@see UserManager} call and render
     * a success / error message.
     *
     * @param InputInterface  $input  CLI arguments
     * @param OutputInterface $output Symfony console output stream
     *
     * @return int Command::SUCCESS on creation, Command::FAILURE when
     *             {@see UserManager::createUser()} throws a domain or
     *             validation error
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');

        try {
            $user = $this->userManager->createUser($email, $password);
        } catch (\DomainException|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Created user "%s" (id=%d).', $user->getUserIdentifier(), (int) $user->getId()));

        return Command::SUCCESS;
    }
}
