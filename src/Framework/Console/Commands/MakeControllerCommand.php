<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:controller', description: 'Create a new controller class')]
class MakeControllerCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The controller name (e.g., User or UserController)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Normalize the class name
        $className = $this->toPascalCase($name);
        if (!str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }

        // Generate route and template names from the base name
        $baseName = str_replace('Controller', '', $className);
        $routeName = strtolower($baseName);
        $templateName = $this->toSnakeCase($baseName);

        $filePath = config('paths.controllers') . '/' . $className . '.php';

        $success = $this->generateFromStub(
            'controller',
            $filePath,
            [
                'class' => $className,
                'route' => $routeName,
                'name' => $routeName,
                'template' => $templateName,
            ],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created controller:</info> {$className}");
            $output->writeln("<comment>Don't forget to create the template:</comment> templates/{$templateName}/index.twig");
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
