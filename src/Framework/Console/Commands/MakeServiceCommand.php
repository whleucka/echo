<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:service', description: 'Create a new service class')]
class MakeServiceCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The service name (e.g., Payment or PaymentService)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Normalize the class name
        $className = $this->toPascalCase($name);
        if (!str_ends_with($className, 'Service')) {
            $className .= 'Service';
        }

        $servicesPath = config('paths.root') . 'app/Services';
        $filePath = $servicesPath . '/' . $className . '.php';

        $success = $this->generateFromStub(
            'service',
            $filePath,
            ['class' => $className],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created service:</info> {$className}");
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
