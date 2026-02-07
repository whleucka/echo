<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'key:generate', description: 'Generate a new APP_KEY')]
class KeyGenerateCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = bin2hex(random_bytes(32));
        $envPath = config('paths.root') . '.env';

        if (!file_exists($envPath)) {
            $output->writeln('<error>.env file not found</error>');
            $output->writeln("APP_KEY={$key}");
            return Command::FAILURE;
        }

        $contents = file_get_contents($envPath);

        if (preg_match('/^APP_KEY=.*/m', $contents)) {
            $contents = preg_replace('/^APP_KEY=.*/m', "APP_KEY={$key}", $contents);
        } else {
            $contents .= "\nAPP_KEY={$key}\n";
        }

        file_put_contents($envPath, $contents);

        $output->writeln("<info>APP_KEY set successfully.</info>");
        $output->writeln("Key: {$key}");

        return Command::SUCCESS;
    }
}
