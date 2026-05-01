<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Routing\Collector;
use Echo\Framework\Routing\RouteCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'sitemap:generate', description: 'Generate sitemap.xml from public GET routes')]
class SitemapGenerateCommand extends Command
{
    /**
     * Middleware names that mark a route as non-public and should be excluded.
     */
    private const EXCLUDED_MIDDLEWARE = ['auth', 'admin', 'api', 'guest', 'csrf', 'benchmark', 'debug'];

    protected function configure(): void
    {
        $this
            ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'Base URL (defaults to config app.url)')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (defaults to public/sitemap.xml)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseUrl = rtrim($input->getOption('url') ?? config('app.url'), '/');
        $outputPath = $input->getOption('output') ?? rtrim(config('paths.public'), '/') . '/sitemap.xml';

        if (!$baseUrl) {
            $output->writeln('<error>No base URL configured. Set APP_URL or pass --url.</error>');
            return Command::FAILURE;
        }

        $routes = $this->loadRoutes();
        $urls = $this->buildUrls($routes, $baseUrl);

        if (empty($urls)) {
            $output->writeln('<comment>No public routes found to include in sitemap.</comment>');
            return Command::FAILURE;
        }

        $xml = $this->renderXml($urls);

        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $output->writeln("<error>Failed to create directory: $dir</error>");
            return Command::FAILURE;
        }

        if (file_put_contents($outputPath, $xml) === false) {
            $output->writeln("<error>Failed to write sitemap to: $outputPath</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>✓ Sitemap written to %s (%d URLs)</info>', $outputPath, count($urls)));
        return Command::SUCCESS;
    }

    private function loadRoutes(): array
    {
        $cache = new RouteCache();
        if ($cache->isCached()) {
            return $cache->getRoutes();
        }

        $collector = new Collector();
        $controllerPath = config('paths.controllers');

        foreach (recursiveFiles($controllerPath) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $className = $this->classNameFromFile($file->getPathname());
            if ($className && class_exists($className)) {
                try {
                    $collector->register($className);
                } catch (\Exception) {
                    // Skip
                }
            }
        }

        return $collector->getRoutes();
    }

    /**
     * @return array<int, array{loc: string, lastmod: string}>
     */
    private function buildUrls(array $routes, string $baseUrl): array
    {
        $urls = [];
        $seen = [];
        $now = date('Y-m-d');

        foreach ($routes as $path => $methods) {
            if (!isset($methods['get'])) {
                continue;
            }

            // Skip parameterized routes — they cannot be enumerated without data.
            if (str_contains($path, '{')) {
                continue;
            }

            foreach ($methods['get'] as $route) {
                if (!empty($route['subdomain'])) {
                    continue;
                }
                if ($this->hasExcludedMiddleware($route['middleware'] ?? [])) {
                    continue;
                }

                $loc = $baseUrl . $path;
                if (isset($seen[$loc])) {
                    continue;
                }
                $seen[$loc] = true;

                $urls[] = ['loc' => $loc, 'lastmod' => $now];
            }
        }

        return $urls;
    }

    private function hasExcludedMiddleware(array $middleware): bool
    {
        foreach ($middleware as $key => $value) {
            $name = is_string($key) ? $key : (string) $value;
            if (in_array(strtolower($name), self::EXCLUDED_MIDDLEWARE, true)) {
                return true;
            }
        }
        return false;
    }

    private function renderXml(array $urls): string
    {
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            $lines[] = '    <lastmod>' . $entry['lastmod'] . '</lastmod>';
            $lines[] = '  </url>';
        }
        $lines[] = '</urlset>';
        return implode("\n", $lines) . "\n";
    }

    private function classNameFromFile(string $filepath): ?string
    {
        $contents = file_get_contents($filepath);
        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $m)) {
            $namespace = $m[1];
        }
        if (preg_match('/class\s+(\w+)/', $contents, $m)) {
            $class = $m[1];
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }
        return $class;
    }
}
