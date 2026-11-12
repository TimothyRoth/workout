<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:delete', description: 'Deletes a user.')]
final class DeleteUserCommand
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(OutputInterface $output, #[Argument] string $username): int
    {
        try {
            $user = $this->users->findByUsername(UserCredentials::username($username)) ?? throw new \InvalidArgumentException("User '$username' not found.");
        } catch (\InvalidArgumentException $e) {
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }
        $this->users->delete($user['id']);
        $output->writeln("User '{$user['username']}' deleted.");

        return Command::SUCCESS;
    }
}
