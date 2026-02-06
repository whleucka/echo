<?php

namespace Echo\Framework\Console\Commands;

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
        $result = db()->fetch("SELECT MAX(batch) as max_batch FROM migrations");
        return ($result['max_batch'] ?? 0) + 1;
    }

    private function getLastBatchNumber(): int
    {
        $result = db()->fetch("SELECT MAX(batch) as max_batch FROM migrations");
        return $result['max_batch'] ?? 0;
    }

    private function getMigrationsFromBatch(int $batch): array
    {
        return db()->fetchAll("SELECT * FROM migrations WHERE batch = ? ORDER BY id DESC", [$batch]);
    }

    private function insertMigration(string $filePath, int $batch = 1): void
    {
        $basename = basename($filePath);
        db()->execute("INSERT INTO migrations (filepath, basename, hash, batch) VALUES (?, ?, ?, ?)", [
            $filePath,
            $basename,
            md5($filePath),
            $batch,
        ]);
    }

    private function deleteMigration(string $filePath): void
    {
        db()->execute("DELETE FROM migrations WHERE hash = ?", [md5($filePath)]);
    }

    private function migrationHashExists(string $hash): ?array
    {
        return db()->fetch("SELECT * FROM migrations WHERE hash = ?", [$hash]) ?: null;
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

        foreach ($migrationFiles as $basename => $filePath) {
            $hash = md5($filePath);
            $migration = $this->migrationHashExists($hash);
            if ($migration) {
                $output->writeln("<info>✓ $basename</info> @ {$migration['created_at']}");
            } else {
                $output->writeln("<comment>✗ $basename</comment>");
            }
        }
    }
}
