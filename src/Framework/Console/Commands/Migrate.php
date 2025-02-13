<?php

namespace Echo\Framework\Console\Commands;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Database migration commands
 */
class Migrate extends \ConsoleKit\Command
{
    /**
     * Does the migrations table exist?
     */
    private function migrationsTableExists()
    {
        return db()->fetch("SHOW TABLES LIKE 'migrations'");
    }

    /**
     * Create migrations table
     */
    private function createMigrationsTable()
    {
        return db()->execute("CREATE TABLE migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            filepath TEXT NOT NULL,
            basename VARCHAR(255) NOT NULL,
            hash CHAR(32) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE (hash)
        )");
    }

    /**
     * Record a successful migration
     */
    private function insertMigration(string $file_path)
    {
        $basename = basename($file_path);
        return db()->execute("INSERT INTO migrations (filepath, basename, hash) 
            VALUES (?, ?, ?)", [
            $file_path,
            $basename,
            md5($file_path),
        ]);
    }

    /**
     * Delete migration record
     */
    private function deleteMigration(string $file_path)
    {
        return db()->execute("DELETE FROM migrations WHERE hash = ?", [
            md5($file_path),
        ]);
    }

    /**
     * Does a migration hash exist in the migrations table?
     */
    private function migrationHashExists(string $hash)
    {
        return db()->fetch("SELECT * 
            FROM migrations 
            WHERE hash = ?", [$hash]);
    }

    /**
     * Get all the migration files from the migrations directory
     */
    private function getMigrationFiles(string $directory)
    {
        if (!file_exists($directory)) {
            throw new \Error("migration directory doesn't exist");
        }
        $migrations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $file_path = $file->getPathname();
                $basename = basename($file_path);
                $migrations[$basename] = $file_path;
            }
        }

        return $migrations;
    }

    /**
     * Get the migration class
     */
    private function getMigration(string $migration_path)
    {
        if (!file_exists($migration_path)) {
            throw new \Error("migration path doesn't exist");
        }
        return require_once $migration_path;
    }

    /**
     * Run 'up' method on migration class
     */
    private function migrationUp(string $file_path)
    {
        $exists = $this->migrationHashExists(md5($file_path));
        if (!$exists) {
            $migration = $this->getMigration($file_path);
            $sql = $migration->up();
            $result = db()->execute($sql);

            if ($result) {
                $this->insertMigration($file_path);
            }
        }
    }

    /**
     * Run 'down' method on migration class
     */
    private function migrationDown(string $file_path)
    {
        $exists = $this->migrationHashExists(md5($file_path));
        if ($exists) {
            $migration = $this->getMigration($file_path);
            $sql = $migration->down();
            $result = db()->execute($sql);

            if ($result) {
                $this->deleteMigration($file_path);
            }
        }
    }

    /**
     * Make sure the migrations table exists
     */
    private function initMigrations()
    {
        if (!$this->migrationsTableExists()) {
            $this->createMigrationsTable();
        }
    }

    /**
     * Display current migration status
     */
    public function executeStatus(array $args, array $options = []): void
    {
        $this->initMigrations();

        $migration_files = $this->getMigrationFiles(config("paths.migrations"));

        foreach ($migration_files as $basename => $file_path) {
            $hash = md5($file_path);
            $migration = $this->migrationHashExists($hash);
            if ($migration) {
                $this->writeln("✓ $basename @ {$migration['created_at']}" . PHP_EOL);
            } else {
                $this->writeln("✗ $basename" . PHP_EOL);
            }
        }
    }

    /**
     * Run all migration files
     * If the database exists, then it will be dropped before creation (be careful)
     */
    public function executeFresh(array $args, array $options = []): void
    {
        $dialog = new \ConsoleKit\Widgets\Dialog($this->console);
        $this->writeln("This operation will drop the current database if it exists.");

        if ($dialog->confirm("Are you sure you want to migrate a fresh database?")) {
            $db_name = config("db.name");
            db()->execute("DROP DATABASE IF EXISTS $db_name");
            db()->execute("CREATE DATABASE $db_name");
            db()->execute("USE $db_name");

            $this->initMigrations();

            $migration_files = $this->getMigrationFiles(config("paths.migrations"));
            foreach ($migration_files as $basename => $file_path) {
                $this->migrationUp($file_path);
            }

            $this->executeStatus([], []);
        }
    }

    /**
     * Run up method on migration file
     */
    public function executeUp(array $args, array $options = []): void
    {
        $this->initMigrations();

        $migration_files = $this->getMigrationFiles(config("paths.migrations"));

        if (empty($args)) {
            $this->writeerr("You must provide migration file names" . PHP_EOL);
            exit;
        }

        foreach ($args as $basename) {
            if (key_exists($basename, $migration_files)) {
                $this->migrationUp($migration_files[$basename]);
            } else {
                $this->writeerr("Migration file doesn't exist" . PHP_EOL);
                exit;
            }
        }

        $this->executeStatus([], []);
    }

    /**
     * Run down method on migration file
     */
    public function executeDown(array $args, array $options = []): void
    {
        $this->initMigrations();

        $migration_files = $this->getMigrationFiles(config("paths.migrations"));

        if (empty($args)) {
            $this->writeerr("You must provide migration file names" . PHP_EOL);
            exit;
        }

        foreach ($args as $basename) {
            if (key_exists($basename, $migration_files)) {
                $this->migrationDown($migration_files[$basename]);
            } else {
                $this->writeerr("Migration file doesn't exist" . PHP_EOL);
                exit;
            }
        }

        $this->executeStatus([], []);
    }

    /**
     * Create a new database migration file
     */
    public function executeCreate(array $args, array $options = []): void
    {
        $this->writeln("wip: create" . PHP_EOL);
    }
}
