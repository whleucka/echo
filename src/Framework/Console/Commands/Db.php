<?php

namespace Echo\Framework\Console\Commands;

/**
 * Database backup and restore commands
 */
class Db extends \ConsoleKit\Command
{
    private string $backupDir;

    public function __construct(\ConsoleKit\Console $console)
    {
        parent::__construct($console);
        $this->backupDir = config('paths.root') . 'storage/backups';
    }

    /**
     * Ensure backup directory exists
     */
    private function ensureBackupDir(): void
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0775, true);
        }
    }

    /**
     * Get database connection details
     */
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

    /**
     * List available backup files
     */
    private function getBackupFiles(): array
    {
        $this->ensureBackupDir();
        
        $files = glob($this->backupDir . '/*.sql.gz');
        $files = array_merge($files, glob($this->backupDir . '/*.sql'));
        
        // Sort by modification time, newest first
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        
        return $files;
    }

    /**
     * Format file size for display
     */
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

    /**
     * Create a database backup
     * 
     * Usage: ./bin/console db backup [filename] [--keep=N]
     * 
     * Options:
     *   --keep=N    Keep only the last N backups (delete older ones)
     */
    public function executeBackup(array $args, array $options = []): void
    {
        $this->ensureBackupDir();
        $db = $this->getDbConfig();

        // Generate filename
        $filename = $args[0] ?? date('Y-m-d_His') . '_' . $db['name'];
        if (!str_ends_with($filename, '.sql.gz') && !str_ends_with($filename, '.sql')) {
            $filename .= '.sql.gz';
        }

        $filepath = $this->backupDir . '/' . $filename;
        $useGzip = str_ends_with($filename, '.gz');

        $this->writeln("Creating backup of database '{$db['name']}'...");

        // Build mysqldump command
        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s',
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['user']),
            escapeshellarg($db['pass']),
            escapeshellarg($db['name'])
        );

        if ($useGzip) {
            $command .= ' | gzip';
        }

        $command .= ' > ' . escapeshellarg($filepath) . ' 2>&1';

        // Execute backup
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
            @unlink($filepath);
            $this->writeerr("✗ Backup failed" . PHP_EOL);
            if (!empty($output)) {
                $this->writeerr("  " . implode("\n  ", $output) . PHP_EOL);
            }
            return;
        }

        $size = $this->formatSize(filesize($filepath));
        $this->writeln("✓ Backup created: $filename ($size)");

        // Auto-cleanup old backups if --keep is specified
        if (isset($options['keep'])) {
            $keep = (int) $options['keep'];
            $this->cleanupOldBackups($keep);
        }
    }

    /**
     * Restore a database from backup
     * 
     * Usage: ./bin/console db restore <filename>
     */
    public function executeRestore(array $args, array $options = []): void
    {
        if (empty($args)) {
            $this->writeerr("Usage: ./bin/console db restore <filename>" . PHP_EOL);
            $this->writeln("");
            $this->writeln("Available backups:");
            $this->executeList([], []);
            return;
        }

        $filename = $args[0];
        $filepath = $this->backupDir . '/' . $filename;

        // Check if file exists (try with and without path)
        if (!file_exists($filepath)) {
            $filepath = $filename; // Maybe they provided full path
            if (!file_exists($filepath)) {
                $this->writeerr("✗ Backup file not found: $filename" . PHP_EOL);
                return;
            }
        }

        $db = $this->getDbConfig();

        // Confirmation prompt
        $dialog = new \ConsoleKit\Widgets\Dialog($this->console);
        $this->writeln("This will restore database '{$db['name']}' from: " . basename($filepath));
        $this->writeln("WARNING: This will overwrite all existing data!");

        if (!$dialog->confirm("Are you sure you want to continue?")) {
            $this->writeln("Restore cancelled.");
            return;
        }

        $this->writeln("Restoring database '{$db['name']}'...");

        $useGzip = str_ends_with($filepath, '.gz');

        // Build mysql restore command
        if ($useGzip) {
            $command = sprintf(
                'gunzip -c %s | mysql -h%s -P%s -u%s -p%s %s 2>&1',
                escapeshellarg($filepath),
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['user']),
                escapeshellarg($db['pass']),
                escapeshellarg($db['name'])
            );
        } else {
            $command = sprintf(
                'mysql -h%s -P%s -u%s -p%s %s < %s 2>&1',
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['user']),
                escapeshellarg($db['pass']),
                escapeshellarg($db['name']),
                escapeshellarg($filepath)
            );
        }

        // Execute restore
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->writeerr("✗ Restore failed" . PHP_EOL);
            if (!empty($output)) {
                $this->writeerr("  " . implode("\n  ", $output) . PHP_EOL);
            }
            return;
        }

        $this->writeln("✓ Database restored successfully from: " . basename($filepath));
    }

    /**
     * List available backup files
     * 
     * Usage: ./bin/console db list
     */
    public function executeList(array $args, array $options = []): void
    {
        $files = $this->getBackupFiles();

        if (empty($files)) {
            $this->writeln("  No backups found in: $this->backupDir");
            return;
        }

        foreach ($files as $file) {
            $name = basename($file);
            $size = $this->formatSize(filesize($file));
            $date = date('Y-m-d H:i:s', filemtime($file));
            $this->writeln(sprintf("  %-45s %10s  %s", $name, $size, $date));
        }

        $this->writeln("");
        $this->writeln("  Total: " . count($files) . " backup(s)");
    }

    /**
     * Clean up old backups, keeping only the most recent N
     * 
     * Usage: ./bin/console db cleanup <keep>
     */
    public function executeCleanup(array $args, array $options = []): void
    {
        if (empty($args)) {
            $this->writeerr("Usage: ./bin/console db cleanup <keep>" . PHP_EOL);
            $this->writeerr("  <keep> = number of backups to keep" . PHP_EOL);
            return;
        }

        $keep = (int) $args[0];
        
        if ($keep < 1) {
            $this->writeerr("✗ Keep value must be at least 1" . PHP_EOL);
            return;
        }

        $this->cleanupOldBackups($keep);
    }

    /**
     * Delete old backups, keeping only the most recent N
     */
    private function cleanupOldBackups(int $keep): void
    {
        $files = $this->getBackupFiles();
        
        if (count($files) <= $keep) {
            $this->writeln("  No cleanup needed (have " . count($files) . ", keeping $keep)");
            return;
        }

        $toDelete = array_slice($files, $keep);
        $deleted = 0;

        foreach ($toDelete as $file) {
            if (unlink($file)) {
                $this->writeln("  Deleted: " . basename($file));
                $deleted++;
            }
        }

        $this->writeln("✓ Cleaned up $deleted old backup(s), kept $keep most recent");
    }
}
