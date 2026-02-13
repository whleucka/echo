<?php

namespace Echo\Framework\Console\Commands;

use App\Models\User;
use App\Services\Auth\AuthService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:user', description: 'Create a new user')]
class MakeUserCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email address')
            ->addArgument('password', InputArgument::REQUIRED, 'User password')
            ->addArgument('role', InputArgument::OPTIONAL, 'User role (standard or admin)', 'standard');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = container()->get(AuthService::class);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $role = $input->getArgument('role');

        if (!in_array($role, ['standard', 'admin'])) {
            $output->writeln('<error>Role must be "standard" or "admin"</error>');
            return Command::FAILURE;
        }

        $user = User::where("email", $email)->first();
        if ($user) {
            $output->writeln('<error>A user with this email already exists</error>');
            return Command::FAILURE;
        }

        $hashedPassword = $service->hashPassword($password);
        $newUser = User::create([
            "first_name" => ucfirst($role),
            "role" => $role,
            "surname" => '',
            "email" => $email,
            "password" => $hashedPassword
        ]);

        if (!$newUser) {
            $output->writeln('<error>Could not create user!</error>');
            return Command::FAILURE;
        }

        if ($role !== 'admin') {
            $created = User::where("email", $email)->first();
            $created?->grantDefaultPermissions();
        }

        $output->writeln("<info>Successfully created $role user: $email</info>");
        return Command::SUCCESS;
    }
}
