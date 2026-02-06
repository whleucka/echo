<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Routing\Collector;
use Echo\Framework\Routing\RouteCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'route:cache', description: 'Cache all application routes')]
class RouteCacheCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Caching routes...");

        $collector = new Collector();
        $controllerPath = config('paths.controllers');

        if (!is_dir($controllerPath)) {
            $output->writeln("<error>Controllers directory not found: $controllerPath</error>");
            return Command::FAILURE;
        }

        $files = recursiveFiles($controllerPath);
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());
                if ($className && class_exists($className)) {
                    try {
                        $collector->register($className);
                    } catch (\Exception $e) {
                        // Skip classes that can't be registered
                    }
                }
            }
        }

        $routes = $collector->getRoutes();
        $cache = new RouteCache();

        if ($cache->cache($routes)) {
            $count = 0;
            foreach ($routes as $methods) {
                $count += count($methods);
            }
            $output->writeln("<info>✓ Routes cached successfully ($count routes)</info>");
            $output->writeln("  Cache file: " . $cache->getCachePath());
            return Command::SUCCESS;
        }

        $output->writeln("<error>Failed to cache routes</error>");
        return Command::FAILURE;
    }

    private function getClassNameFromFile(string $filepath): ?string
    {
        $contents = file_get_contents($filepath);
        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return $class;
    }
}
