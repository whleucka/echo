<?php

namespace Echo\Framework\Console\Commands;

/**
 * Storage management commands
 */
class Storage extends \ConsoleKit\Command
{
    private array $directories = [
        'storage',
        'templates/.cache',
    ];

    /**
     * Fix ownership of storage and cache directories
     * 
     * Usage: ./bin/console storage fix [--user=www-data] [--group=www-data]
     * 
     * Note: This command should typically be run inside the Docker container
     * or use: docker compose exec php ./bin/console storage fix
     */
    public function executeFix(array $args, array $options = []): void
    {
        $user = $options['user'] ?? 'www-data';
        $group = $options['group'] ?? 'www-data';
        $ownership = "$user:$group";

        $root = config('paths.root');

        $this->writeln("Fixing ownership to $ownership...");

        foreach ($this->directories as $relative) {
            $dir = $root . $relative;

            if (!is_dir($dir)) {
                $this->writeln("  Creating directory: $relative");
                if (!mkdir($dir, 0775, true)) {
                    $this->writeerr("  ✗ Failed to create: $relative" . PHP_EOL);
                    continue;
                }
            }

            $command = sprintf('chown -R %s %s 2>&1', escapeshellarg($ownership), escapeshellarg($dir));
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $this->writeln("  ✓ $relative");
            } else {
                $this->writeerr("  ✗ $relative - " . implode(' ', $output) . PHP_EOL);
                $this->writeerr("    (Try: docker compose exec php ./bin/console storage fix)" . PHP_EOL);
            }
        }

        $this->writeln("Done.");
    }
}
