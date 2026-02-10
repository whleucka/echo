<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:middleware', description: 'Create a new middleware class')]
class MakeMiddlewareCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The middleware name (e.g., RateLimit)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Normalize the class name
        $className = $this->toPascalCase($name);

        $middlewarePath = config('paths.root') . 'app/Http/Middleware';
        $filePath = $middlewarePath . '/' . $className . '.php';

        $success = $this->generateFromStub(
            'middleware',
            $filePath,
            ['class' => $className],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created middleware:</info> {$className}");
            $output->writeln("<comment>Register it in:</comment> app/Http/Kernel.php");
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
