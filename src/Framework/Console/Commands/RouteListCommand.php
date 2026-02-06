<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Routing\Collector;
use Echo\Framework\Routing\RouteCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'route:list', description: 'List all registered routes')]
class RouteListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = new RouteCache();

        if ($cache->isCached()) {
            $routes = $cache->getRoutes();
            $output->writeln("Routes (from cache):");
        } else {
            $collector = new Collector();
            $controllerPath = config('paths.controllers');

            $files = recursiveFiles($controllerPath);
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $className = $this->getClassNameFromFile($file->getPathname());
                    if ($className && class_exists($className)) {
                        try {
                            $collector->register($className);
                        } catch (\Exception $e) {
                            // Skip
                        }
                    }
                }
            }
            $routes = $collector->getRoutes();
            $output->writeln("Routes (not cached):");
        }

        if (empty($routes)) {
            $output->writeln("  No routes found.");
            return Command::SUCCESS;
        }

        foreach ($routes as $path => $methods) {
            foreach ($methods as $method => $route) {
                $middleware = !empty($route['middleware']) ? '[' . implode(', ', $route['middleware']) . ']' : '';
                $output->writeln(sprintf(
                    "  %-7s %-40s -> %s::%s %s",
                    strtoupper($method),
                    $path,
                    $this->getShortClassName($route['controller']),
                    $route['method'],
                    $middleware
                ));
            }
        }

        return Command::SUCCESS;
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

    private function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }
}
