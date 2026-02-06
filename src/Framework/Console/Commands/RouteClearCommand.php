<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Routing\RouteCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'route:clear', description: 'Clear the route cache')]
class RouteClearCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = new RouteCache();

        if (!$cache->isCached()) {
            $output->writeln("Route cache is already empty.");
            return Command::SUCCESS;
        }

        if ($cache->clear()) {
            $output->writeln("<info>✓ Route cache cleared successfully</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<error>Failed to clear route cache</error>");
        return Command::FAILURE;
    }
}
