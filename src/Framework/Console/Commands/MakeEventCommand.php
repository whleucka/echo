<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:event', description: 'Create a new event class')]
class MakeEventCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The event name (e.g., UserRegistered)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Normalize the class name
        $className = $this->toPascalCase($name);

        $eventPath = config('paths.root') . 'app/Events';
        $filePath = $eventPath . '/' . $className . '.php';

        $success = $this->generateFromStub(
            'event',
            $filePath,
            ['class' => $className],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created event:</info> App\\Events\\{$className}");
            $output->writeln("<comment>Register listeners in:</comment> app/Providers/EventServiceProvider.php");
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
