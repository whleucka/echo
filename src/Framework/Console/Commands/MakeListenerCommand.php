<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:listener', description: 'Create a new event listener class')]
class MakeListenerCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The listener name (e.g., SendWelcomeEmail)')
            ->addOption('event', 'e', InputOption::VALUE_OPTIONAL, 'The event class to type-hint (e.g., UserRegistered)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $eventName = $input->getOption('event');

        // Normalize the class name
        $className = $this->toPascalCase($name);

        $listenerPath = config('paths.root') . 'app/Listeners';
        $filePath = $listenerPath . '/' . $className . '.php';

        $success = $this->generateFromStub(
            'listener',
            $filePath,
            ['class' => $className],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created listener:</info> App\\Listeners\\{$className}");
            if ($eventName) {
                $eventClass = $this->toPascalCase($eventName);
                $output->writeln("<comment>Don't forget to register it in app/Providers/EventServiceProvider.php:</comment>");
                $output->writeln("  App\\Events\\{$eventClass}::class => [");
                $output->writeln("      App\\Listeners\\{$className}::class,");
                $output->writeln("  ],");
            } else {
                $output->writeln("<comment>Register it in:</comment> app/Providers/EventServiceProvider.php");
            }
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
