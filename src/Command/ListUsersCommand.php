<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:list', description: 'Lists the users.')]
final class ListUsersCommand
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        $users = $this->users->findAll();
        if ([] === $users) {
            $output->writeln('No users yet. Create one with app:user:create.');
        }
        foreach ($users as $user) {
            $output->writeln(\sprintf('%-4d %-30s %s', $user['id'], $user['username'], $user['created_at']));
        }

        return Command::SUCCESS;
    }
}
