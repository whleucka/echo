<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;

trait MakeCommandTrait
{
    /**
     * Get the stubs directory path
     */
    protected function getStubsPath(): string
    {
        return config('paths.root') . 'stubs';
    }

    /**
     * Load a stub file and return its contents
     */
    protected function getStub(string $stubName): string
    {
        $stubPath = $this->getStubsPath() . '/' . $stubName . '.stub';

        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub file not found: {$stubPath}");
        }

        return file_get_contents($stubPath);
    }

    /**
     * Replace placeholders in stub content
     * 
     * Placeholders use {{ name }} syntax
     */
    protected function replacePlaceholders(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }

        return $stub;
    }

    /**
     * Write generated content to a file
     */
    protected function writeFile(string $path, string $content, OutputInterface $output): bool
    {
        // Ensure directory exists
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                $output->writeln("<error>Failed to create directory: {$directory}</error>");
                return false;
            }
        }

        // Check if file already exists
        if (file_exists($path)) {
            $output->writeln("<error>File already exists: {$path}</error>");
            return false;
        }

        // Write the file
        $result = file_put_contents($path, $content);

        if ($result === false) {
            $output->writeln("<error>Failed to write file: {$path}</error>");
            return false;
        }

        return true;
    }

    /**
     * Generate a file from a stub
     */
    protected function generateFromStub(
        string $stubName,
        string $outputPath,
        array $replacements,
        OutputInterface $output
    ): bool {
        $stub = $this->getStub($stubName);
        $content = $this->replacePlaceholders($stub, $replacements);

        return $this->writeFile($outputPath, $content, $output);
    }

    /**
     * Convert a name to PascalCase (e.g., user_controller -> UserController)
     */
    protected function toPascalCase(string $name): string
    {
        // Handle snake_case
        $name = str_replace('_', ' ', $name);
        // Handle kebab-case
        $name = str_replace('-', ' ', $name);
        // Capitalize each word and remove spaces
        return str_replace(' ', '', ucwords($name));
    }

    /**
     * Convert a name to snake_case (e.g., UserController -> user_controller)
     */
    protected function toSnakeCase(string $name): string
    {
        // Insert underscore before uppercase letters and convert to lowercase
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * Convert a class name to a table name (e.g., User -> users, UserProfile -> user_profiles)
     */
    protected function toTableName(string $className): string
    {
        // Convert to snake_case and pluralize (simple pluralization)
        $snake = $this->toSnakeCase($className);

        // Simple pluralization rules
        if (str_ends_with($snake, 'y')) {
            return substr($snake, 0, -1) . 'ies';
        } elseif (str_ends_with($snake, 's') || str_ends_with($snake, 'x') || str_ends_with($snake, 'ch') || str_ends_with($snake, 'sh')) {
            return $snake . 'es';
        }

        return $snake . 's';
    }
}
