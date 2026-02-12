<?php

namespace Echo\Framework\Console\Commands;

use App\Models\User;
use App\Services\Auth\AuthService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'admin:new', description: 'Create a new admin user')]
class AdminNewCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email address')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = container()->get(AuthService::class);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Check for existing user
        $user = User::where("email" , $email)->get();
        
        if ($user) {
            $output->writeln('<error>This admin user already exists</error>');
            return Command::FAILURE;
        }

        $hashed_password = $service->hashPassword($password);
        $admin_user = User::create([
            "first_name" => "Admin",
            "role" => "admin",
            "surname" => '',
            "email" => $email,
            "password" => $hashed_password
        ]);

        if (!$admin_user) {
            $output->writeln('<error>Couldn not create admin!</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ Successfully created admin user: $email</info>");
        return Command::SUCCESS;
    }
}
