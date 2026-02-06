<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'db:list', description: 'List available database backups')]
class DbListCommand extends Command
{
    use DbTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = $this->getBackupFiles();

        if (empty($files)) {
            $output->writeln("  No backups found in: " . $this->getBackupDir());
            return Command::SUCCESS;
        }

        foreach ($files as $file) {
            $name = basename($file);
            $size = $this->formatSize(filesize($file));
            $date = date('Y-m-d H:i:s', filemtime($file));
            $output->writeln(sprintf("  %-45s %10s  %s", $name, $size, $date));
        }

        $output->writeln("");
        $output->writeln("  Total: " . count($files) . " backup(s)");
        return Command::SUCCESS;
    }
}
