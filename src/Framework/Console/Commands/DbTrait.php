<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;

trait DbTrait
{
    private function getBackupDir(): string
    {
        return config('paths.root') . 'storage/backups';
    }

    private function ensureBackupDir(): void
    {
        $dir = $this->getBackupDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function getDbConfig(): array
    {
        return [
            'host' => config('db.host'),
            'port' => config('db.port'),
            'name' => config('db.name'),
            'user' => config('db.username'),
            'pass' => config('db.password'),
        ];
    }

    private function getBackupFiles(): array
    {
        $this->ensureBackupDir();
        $dir = $this->getBackupDir();

        $files = glob($dir . '/*.sql.gz');
        $files = array_merge($files, glob($dir . '/*.sql'));

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        return $files;
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function cleanupOldBackups(int $keep, OutputInterface $output): void
    {
        $files = $this->getBackupFiles();

        if (count($files) <= $keep) {
            $output->writeln("  No cleanup needed (have " . count($files) . ", keeping $keep)");
            return;
        }

        $toDelete = array_slice($files, $keep);
        $deleted = 0;

        foreach ($toDelete as $file) {
            if (unlink($file)) {
                $output->writeln("  Deleted: " . basename($file));
                $deleted++;
            }
        }

        $output->writeln("<info>✓ Cleaned up $deleted old backup(s), kept $keep most recent</info>");
    }
}
