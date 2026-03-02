<?php

require_once __DIR__ . '/../vendor/autoload.php';

$logsDir = config("paths.logs");
$maxAgeDays = 7;
$deleted = 0;
$cutoff = time() - ($maxAgeDays * 86400);

$files = glob($logsDir . "*.log");

foreach ($files as $file) {
    if (is_file($file) && filemtime($file) < $cutoff) {
        if (unlink($file)) {
            $deleted++;
        }
    }
}

printf(
    "%s log_cleanup: deleted=%d files older than %d days\n",
    date('Y-m-d H:i:s'),
    $deleted,
    $maxAgeDays,
);
