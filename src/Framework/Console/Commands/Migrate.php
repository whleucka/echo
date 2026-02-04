<?php

namespace Echo\Framework\Console\Commands;

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
            batch INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE (hash)
        )");
    }

    /**
     * Get the next batch number
     */
    private function getNextBatchNumber(): int
    {
        $result = db()->fetch("SELECT MAX(batch) as max_batch FROM migrations");
        return ($result['max_batch'] ?? 0) + 1;
    }

    /**
     * Get the last batch number
     */
    private function getLastBatchNumber(): int
    {
        $result = db()->fetch("SELECT MAX(batch) as max_batch FROM migrations");
        return $result['max_batch'] ?? 0;
    }

    /**
     * Get migrations from a specific batch
     */
    private function getMigrationsFromBatch(int $batch): array
    {
        return db()->fetchAll("SELECT * FROM migrations WHERE batch = ? ORDER BY id DESC", [$batch]);
    }

    /**
     * Record a successful migration
     */
    private function insertMigration(string $file_path, int $batch = 1)
    {
        $basename = basename($file_path);
        return db()->execute("INSERT INTO migrations (filepath, basename, hash, batch)
            VALUES (?, ?, ?, ?)", [
            $file_path,
            $basename,
            md5($file_path),
            $batch,
        ]);
    }

    /**
     * Delete migration record
     */
    private function deleteMigration(string $file_path)
    {
        return db()->execute("DELETE FROM migrations 
            WHERE hash = ?", [
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
        $files = recursiveFiles($directory);

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $file_path = $file->getPathname();
                $basename = basename($file_path);
                $migrations[$basename] = $file_path;
            }
        }
        asort($migrations);
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
    private function migrationUp(string $file_path, int $batch = 1)
    {
        $exists = $this->migrationHashExists(md5($file_path));
        if ($exists) {
            return false;
        }

        $migration = $this->getMigration($file_path);

        db()->beginTransaction();
        try {
            $sql = $migration->up();
            $result = db()->execute($sql);

            if ($result) {
                $this->insertMigration($file_path, $batch);
                db()->commit();
                return true;
            } else {
                db()->rollback();
                throw new \Exception("Migration failed: " . basename($file_path));
            }
        } catch (\Exception $e) {
            db()->rollback();
            throw $e;
        }
    }

    /**
     * Run 'down' method on migration class
     */
    private function migrationDown(string $file_path)
    {
        $exists = $this->migrationHashExists(md5($file_path));
        if (!$exists) {
            return;
        }

        $migration = $this->getMigration($file_path);

        db()->beginTransaction();
        try {
            $sql = $migration->down();
            $result = db()->execute($sql);

            if ($result) {
                $this->deleteMigration($file_path);
                db()->commit();
            } else {
                db()->rollback();
                throw new \Exception("Migration rollback failed: " . basename($file_path));
            }
        } catch (\Exception $e) {
            db()->rollback();
            throw $e;
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

    private function newDatabase()
    {
        $db_name = config("db.name");
        db()->execute("CREATE DATABASE $db_name");
        db()->execute("USE $db_name");
        $this->writeln("✓ successfully created new database $db_name");
    }

    private function dropDatabase()
    {
        $db_name = config("db.name");
        db()->execute("DROP DATABASE IF EXISTS $db_name");
        $this->writeln("✓ successfully deleted database $db_name");
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
                $this->writeln("✓ $basename @ {$migration['created_at']}");
            } else {
                $this->writeln("✗ $basename");
            }
        }
    }

    /**
     * Drop database and run all migration files
     * If the database exists, then it will be dropped before creation (be careful)
     */
    public function executeFresh(array $args, array $options = []): void
    {
        $dialog = new \ConsoleKit\Widgets\Dialog($this->console);
        $this->writeln("This operation will drop the current database if it exists.");

        if ($dialog->confirm("Are you sure you want to migrate a fresh database?")) {
            $this->dropDatabase();
            $this->newDatabase();

            $this->initMigrations();

            $migration_files = $this->getMigrationFiles(config("paths.migrations"));
            foreach ($migration_files as $basename => $file_path) {
                $this->migrationUp($file_path);
            }

            $this->executeStatus([], []);
        }
    }

    /**
     * Run all pending migration files
     */
    public function executeRun(array $args, array $options = []): void
    {
        $this->initMigrations();

        $this->writeln("Running migrations...");

        $batch = $this->getNextBatchNumber();
        $ran = false;

        $migration_files = $this->getMigrationFiles(config("paths.migrations"));
        foreach ($migration_files as $basename => $file_path) {
            $hash = md5($file_path);
            $migration = $this->migrationHashExists($hash);
            if (!$migration) {
                if ($this->migrationUp($file_path, $batch)) {
                    $ran = true;
                }
            }
        }

        if (!$ran) {
            $this->writeln("Nothing to migrate.");
        }

        $this->executeStatus([], []);
    }

    /**
     * Rollback the last batch of migrations
     */
    public function executeRollback(array $args, array $options = []): void
    {
        $this->initMigrations();

        $steps = isset($options['steps']) ? (int) $options['steps'] : 1;

        $this->writeln("Rolling back migrations...");

        $lastBatch = $this->getLastBatchNumber();

        if ($lastBatch === 0) {
            $this->writeln("Nothing to rollback.");
            return;
        }

        $rolledBack = 0;
        for ($i = 0; $i < $steps && $lastBatch > 0; $i++) {
            $migrations = $this->getMigrationsFromBatch($lastBatch);

            foreach ($migrations as $migration) {
                $this->migrationDown($migration['filepath']);
                $this->writeln("Rolled back: " . $migration['basename']);
                $rolledBack++;
            }

            $lastBatch--;
        }

        if ($rolledBack === 0) {
            $this->writeln("Nothing to rollback.");
        } else {
            $this->writeln("Rolled back $rolledBack migration(s).");
        }

        $this->executeStatus([], []);
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
        if (empty($args)) {
            $this->writeerr("You must provide migration name" . PHP_EOL);
            exit;
        }
        $migration_path = config("paths.migrations");
        $table = $args[0];
        $time = time();
        $file_name = sprintf("%s_create_%s.php", $time, $table);
        $file_path = sprintf("%s/%s", $migration_path, $file_name);
        $create = <<<EOT
<?php

use Echo\Interface\Database\Migration;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements Migration
{
    private string \$table = "{table}";

    public function up(): string
    {
         return Schema::create(\$this->table, function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
            \$table->primaryKey("id");
        });
    }

    public function down(): string
    {
         return Schema::drop(\$this->table);
    }
};
EOT;
        $migration = str_replace("{table}", $table, $create);
        $result = file_put_contents($file_path, $migration);
        if ($result) {
            $this->writeln("✓ successfully created $file_name");
        }
    }
}
