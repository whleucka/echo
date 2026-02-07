<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Echo\Framework\Mail\Mailer;
use Echo\Framework\Mail\EmailQueue;

// Boot just enough of the app to use config, db, and services
$mailer = new Mailer(
    host: config('mail.host'),
    port: (int) config('mail.port'),
    username: config('mail.username'),
    password: config('mail.password'),
    encryption: config('mail.encryption'),
    fromAddress: config('mail.from_address'),
    fromName: config('mail.from_name'),
);

$queue = new EmailQueue($mailer);
$result = $queue->process();

printf(
    "%s mail_worker: sent=%d failed=%d\n",
    date('Y-m-d H:i:s'),
    $result['sent'],
    $result['failed'],
);
