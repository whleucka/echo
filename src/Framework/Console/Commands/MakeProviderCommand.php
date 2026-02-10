<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:provider', description: 'Create a new service provider class')]
class MakeProviderCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The provider name (e.g., Cache or CacheServiceProvider)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Normalize the class name
        $className = $this->toPascalCase($name);
        if (!str_ends_with($className, 'ServiceProvider')) {
            $className .= 'ServiceProvider';
        }

        $providersPath = config('paths.root') . 'app/Providers';
        $filePath = $providersPath . '/' . $className . '.php';

        $success = $this->generateFromStub(
            'provider',
            $filePath,
            ['class' => $className],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created provider:</info> {$className}");
            $output->writeln("<comment>Register it in:</comment> config/providers.php");
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
