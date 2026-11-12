<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:password', description: 'Sets a new password; remembered logins of that user stop working.')]
final class ChangePasswordCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserCredentials $credentials,
    ) {
    }

    public function __invoke(OutputInterface $output, #[Argument] string $username, #[Argument] string $password): int
    {
        try {
            $user = $this->users->findByUsername(UserCredentials::username($username)) ?? throw new \InvalidArgumentException("User '$username' not found.");
            $this->users->updatePassword($user['id'], $this->credentials->hash($password));
        } catch (\InvalidArgumentException $e) {
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }
        $output->writeln("Password of '{$user['username']}' changed; remembered logins were revoked.");

        return Command::SUCCESS;
    }
}
