<?php

namespace Echo\Framework\Console\Commands;

use App\Models\Migration;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

trait MigrateTrait
{
    private function migrationsTableExists(): bool
    {
        return (bool) db()->fetch("SHOW TABLES LIKE 'migrations'");
    }

    private function createMigrationsTable(): void
    {
        db()->execute("CREATE TABLE migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            filepath TEXT NOT NULL,
            basename VARCHAR(255) NOT NULL,
            hash CHAR(32) NOT NULL,
            batch INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE (hash)
        )");
    }

    private function getNextBatchNumber(): int
    {
        $maxBatch = Migration::maxAll('batch');
        return ($maxBatch ?? 0) + 1;
    }

    private function getLastBatchNumber(): int
    {
        return Migration::maxAll('batch') ?? 0;
    }

    private function getMigrationsFromBatch(int $batch): array
    {
        $migrations = Migration::where('batch', (string)$batch)
            ->orderBy('id', 'DESC')
            ->get();

        if (!$migrations) {
            return [];
        }

        // Convert to array format for compatibility
        return array_map(fn(Migration $m) => $m->getAttributes(), $migrations);
    }

    private function insertMigration(string $filePath, int $batch = 1): void
    {
        Migration::create([
            'filepath' => $filePath,
            'basename' => basename($filePath),
            'hash' => md5($filePath),
            'batch' => $batch,
        ]);
    }

    private function deleteMigration(string $filePath): void
    {
        $migration = Migration::where('hash', md5($filePath))->first();
        if ($migration) {
            $migration->delete();
        }
    }

    private function migrationHashExists(string $hash): ?array
    {
        $migration = Migration::where('hash', $hash)->first();
        return $migration ? $migration->getAttributes() : null;
    }

    private function getMigrationFiles(string $directory): array
    {
        if (!file_exists($directory)) {
            throw new \RuntimeException("Migration directory doesn't exist: $directory");
        }

        $migrations = [];
        $files = recursiveFiles($directory);

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();
                $basename = basename($filePath);
                $migrations[$basename] = $filePath;
            }
        }
        asort($migrations);
        return $migrations;
    }

    private function getMigration(string $migrationPath)
    {
        if (!file_exists($migrationPath)) {
            throw new \RuntimeException("Migration path doesn't exist: $migrationPath");
        }
        return require $migrationPath;
    }

    private function migrationUp(string $filePath, int $batch = 1): bool
    {
        $exists = $this->migrationHashExists(md5($filePath));
        if ($exists) {
            return false;
        }

        $migration = $this->getMigration($filePath);

        db()->beginTransaction();
        try {
            $sql = $migration->up();
            $result = db()->execute($sql);

            if ($result) {
                $this->insertMigration($filePath, $batch);
                db()->commit();
                return true;
            } else {
                db()->rollback();
                throw new \RuntimeException("Migration failed: " . basename($filePath));
            }
        } catch (\Exception $e) {
            db()->rollback();
            throw $e;
        }
    }

    private function migrationDown(string $filePath): void
    {
        $exists = $this->migrationHashExists(md5($filePath));
        if (!$exists) {
            return;
        }

        $migration = $this->getMigration($filePath);

        db()->beginTransaction();
        try {
            $sql = $migration->down();
            $result = db()->execute($sql);

            if ($result) {
                $this->deleteMigration($filePath);
                db()->commit();
            } else {
                db()->rollback();
                throw new \RuntimeException("Migration rollback failed: " . basename($filePath));
            }
        } catch (\Exception $e) {
            db()->rollback();
            throw $e;
        }
    }

    private function initMigrations(): void
    {
        if (!$this->migrationsTableExists()) {
            $this->createMigrationsTable();
        }
    }

    private function newDatabase(): void
    {
        $dbName = config("db.name");
        db()->execute("CREATE DATABASE $dbName");
        db()->execute("USE $dbName");
    }

    private function dropDatabase(): void
    {
        $dbName = config("db.name");
        db()->execute("DROP DATABASE IF EXISTS $dbName");
    }

    private function printStatus(OutputInterface $output): void
    {
        $migrationFiles = $this->getMigrationFiles(config("paths.migrations"));

        if (empty($migrationFiles)) {
            $output->writeln('<comment>No migration files found.</comment>');
            return;
        }

        $ran = 0;
        $pending = 0;
        $rows = [];

        foreach ($migrationFiles as $basename => $filePath) {
            $hash = md5($filePath);
            $migration = $this->migrationHashExists($hash);

            if ($migration) {
                $ran++;
                $rows[] = [
                    '<info>Ran</info>',
                    $basename,
                    $migration['batch'],
                    $migration['created_at'],
                ];
            } else {
                $pending++;
                $rows[] = [
                    '<comment>Pending</comment>',
                    $basename,
                    '-',
                    '-',
                ];
            }
        }

        $table = new Table($output);
        $table->setHeaderTitle('Migrations');
        $table->setHeaders(['Status', 'Migration', 'Batch', 'Ran at']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');
        $total = $ran + $pending;
        $output->writeln("  <info>$ran ran</info>, <comment>$pending pending</comment>, $total total");
    }
}
