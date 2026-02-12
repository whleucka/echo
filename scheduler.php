<?php 

require_once __DIR__.'/vendor/autoload.php';

use GO\Scheduler;

// Create a new scheduler
$scheduler = new Scheduler();

$jobs = config("paths.jobs");
$logs = config("paths.logs");

// Mail worker - process queued emails
$scheduler->php($jobs . "/mail_worker.php")
    ->everyMinute()
    ->output($logs . "mail-worker-" . date("Y-m-d") . ".log", true);

// Let the scheduler execute jobs which are due.
$scheduler->run();
