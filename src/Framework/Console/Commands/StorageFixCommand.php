<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'storage:fix', description: 'Fix ownership of storage and cache directories')]
class StorageFixCommand extends Command
{
    private array $directories = [
        'storage',
        'templates/.cache',
    ];

    protected function configure(): void
    {
        $this
            ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'Owner user', 'www-data')
            ->addOption('group', 'g', InputOption::VALUE_OPTIONAL, 'Owner group', 'www-data')
            ->setHelp('This command should typically be run inside the Docker container or use: docker compose exec php ./bin/console storage fix');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $input->getOption('user');
        $group = $input->getOption('group');
        $ownership = "$user:$group";

        $root = config('paths.root');

        $output->writeln("Fixing ownership to $ownership...");

        foreach ($this->directories as $relative) {
            $dir = $root . $relative;

            if (!is_dir($dir)) {
                $output->writeln("  Creating directory: $relative");
                if (!mkdir($dir, 0775, true)) {
                    $output->writeln("<error>  ✗ Failed to create: $relative</error>");
                    continue;
                }
            }

            $command = sprintf('chown -R %s %s 2>&1', escapeshellarg($ownership), escapeshellarg($dir));
            $cmdOutput = [];
            $returnCode = 0;
            exec($command, $cmdOutput, $returnCode);

            if ($returnCode === 0) {
                $output->writeln("<info>  ✓ $relative</info>");
            } else {
                $output->writeln("<error>  ✗ $relative - " . implode(' ', $cmdOutput) . "</error>");
                $output->writeln("    <comment>(Try: docker compose exec php ./bin/console storage fix)</comment>");
            }
        }

        $output->writeln("Done.");
        return Command::SUCCESS;
    }
}
