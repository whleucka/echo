<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Admin\DashboardService;
use App\Services\Admin\SystemHealthService;
use Echo\Framework\Mail\Mailable;
use Echo\Framework\Mail\Mailer;

$contactEmail = config('company.contact_email');

if (empty($contactEmail)) {
    printf("%s daily_report: skipped — no contact email configured\n", date('Y-m-d H:i:s'));
    exit(0);
}

// Build report data via the DashboardService
$service = new DashboardService(new SystemHealthService());
$data = $service->getDailyReportData();

$appName = config('app.name') ?? 'Echo';
$subject = sprintf('%s Daily Report — %s', $appName, $data['date_short']);

// Compose the email
$mailable = Mailable::create()
    ->to($contactEmail)
    ->subject($subject)
    ->template('emails/daily-report.html.twig', [
        'app_name' => $appName,
        'data' => $data,
    ]);

// Queue it for delivery by the mail worker
$mailer = new Mailer(
    host: config('mail.host'),
    port: (int) config('mail.port'),
    username: config('mail.username'),
    password: config('mail.password'),
    encryption: config('mail.encryption'),
    fromAddress: config('mail.from_address'),
    fromName: config('mail.from_name'),
);

try {
    $mailer->queue($mailable);
    printf(
        "%s daily_report: queued for %s (date=%s, requests=%d, new_users=%d)\n",
        date('Y-m-d H:i:s'),
        $contactEmail,
        $data['date_short'],
        $data['requests']['total'],
        $data['users']['new'],
    );
} catch (\Throwable $e) {
    fprintf(
        STDERR,
        "%s daily_report: error — %s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
    );
    exit(1);
}
