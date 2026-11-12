<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:create', description: 'Creates a user who can log in.')]
final class CreateUserCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserCredentials $credentials,
    ) {
    }

    public function __invoke(OutputInterface $output, #[Argument] string $username, #[Argument] string $password): int
    {
        try {
            $username = UserCredentials::username($username);
            if (null !== $this->users->findByUsername($username)) {
                throw new \InvalidArgumentException("User '$username' already exists.");
            }
            $this->users->create($username, $this->credentials->hash($password));
        } catch (\InvalidArgumentException $e) {
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }
        $output->writeln("User '$username' created successfully.");

        return Command::SUCCESS;
    }
}
