<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'server', description: 'Start the local development server')]
class ServerCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dev = config('dev');
        $output->writeln("<info>Starting development server on {$dev['server']}:{$dev['port']}</info>");
        passthru("php -S {$dev['server']}:{$dev['port']} -t public/");
        return Command::SUCCESS;
    }
}
