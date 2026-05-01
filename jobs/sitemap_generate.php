<?php

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$console = $root . '/bin/console';

$cmd = sprintf('%s %s sitemap:generate 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($console));
exec($cmd, $output, $exitCode);

printf(
    "%s sitemap_generate: exit=%d\n%s\n",
    date('Y-m-d H:i:s'),
    $exitCode,
    implode("\n", $output)
);
