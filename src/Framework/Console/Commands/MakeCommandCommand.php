<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:command', description: 'Create a new console command class')]
class MakeCommandCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The command name (e.g., SendEmails or send:emails)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // If name contains colon, it's a command signature like "send:emails"
        if (str_contains($name, ':')) {
            $commandName = $name;
            // Convert send:emails to SendEmailsCommand
            $parts = explode(':', $name);
            $className = '';
            foreach ($parts as $part) {
                $className .= $this->toPascalCase($part);
            }
            $className .= 'Command';
        } else {
            // It's a class name like SendEmails
            $className = $this->toPascalCase($name);
            if (!str_ends_with($className, 'Command')) {
                $className .= 'Command';
            }
            // Convert SendEmailsCommand to send:emails
            $baseName = str_replace('Command', '', $className);
            $commandName = $this->toSnakeCase($baseName);
            $commandName = str_replace('_', ':', $commandName);
        }

        $commandsPath = config('paths.root') . 'app/Console/Commands';
        $filePath = $commandsPath . '/' . $className . '.php';

        $success = $this->generateFromStub(
            'command',
            $filePath,
            [
                'class' => $className,
                'name' => $commandName,
                'description' => 'Description of the command',
            ],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created command:</info> {$className}");
            $output->writeln("<comment>Command signature:</comment> {$commandName}");
            $output->writeln("<comment>Register it in:</comment> app/Console/Kernel.php");
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
