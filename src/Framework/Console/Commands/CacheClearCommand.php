<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Routing\RouteCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:clear', description: 'Clear all application caches (templates, routes, widgets)')]
class CacheClearCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Twig template cache
        $templateCache = config('paths.template_cache');
        if ($templateCache && is_dir($templateCache)) {
            $count = $this->clearDirectory($templateCache);
            $output->writeln("<info>✓ Twig template cache cleared ($count files)</info>");
        } else {
            $output->writeln("  Twig template cache directory not found, skipping");
        }

        // Route cache
        $routeCache = new RouteCache();
        if ($routeCache->isCached()) {
            $routeCache->clear();
            $output->writeln("<info>✓ Route cache cleared</info>");
        } else {
            $output->writeln("  Route cache already empty, skipping");
        }

        // Widget cache
        $widgetCacheDir = config('paths.cache') ?? sys_get_temp_dir();
        if (is_dir($widgetCacheDir)) {
            $count = 0;
            foreach (glob($widgetCacheDir . '/widget_*.cache') as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
            if ($count > 0) {
                $output->writeln("<info>✓ Widget cache cleared ($count files)</info>");
            } else {
                $output->writeln("  No widget cache files found, skipping");
            }
        }

        $output->writeln('');
        $output->writeln('<info>All caches cleared.</info>');

        return Command::SUCCESS;
    }

    private function clearDirectory(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->getFilename() === '.gitignore') {
                continue;
            }
            if ($item->isFile()) {
                if (@unlink($item->getPathname())) {
                    $count++;
                }
            } elseif ($item->isDir()) {
                @rmdir($item->getPathname());
            }
        }

        return $count;
    }
}
