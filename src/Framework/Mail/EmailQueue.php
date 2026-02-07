<?php

namespace Echo\Framework\Mail;

use App\Models\EmailJob;
use Echo\Framework\Audit\AuditLogger;
use Echo\Framework\Database\QueryBuilder;
use Echo\Framework\Logging\Logger;

/**
 * Processes pending email jobs from the email_jobs table.
 *
 * Called by the scheduler's mail_worker job.
 */
class EmailQueue
{
    private Logger $log;

    public function __construct(
        private Mailer $mailer,
    ) {
        $this->log = logger()->channel('mail');
    }

    /**
     * Process a batch of pending email jobs.
     *
     * @return array{sent: int, failed: int}
     */
    public function process(): array
    {
        $batchSize = config('mail.batch_size') ?? 20;
        $retryDelay = config('mail.retry_delay_minutes') ?? 5;
        $sent = 0;
        $failed = 0;

        $jobs = $this->getPendingJobs($batchSize, $retryDelay);

        $this->log->info('Queue processing started', ['pending' => count($jobs)]);

        foreach ($jobs as $job) {
            $this->markInProgress($job);

            try {
                $payload = json_decode($job->payload, true);
                if (!$payload) {
                    throw new \RuntimeException("Invalid payload for email job #{$job->id}");
                }

                $this->mailer->sendFromPayload($payload);
                $this->markSent($job);
                $sent++;
            } catch (\Throwable $e) {
                $this->markFailed($job, $e->getMessage());
                $failed++;
            }
        }

        $this->log->info('Queue processing complete', ['sent' => $sent, 'failed' => $failed]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Fetch pending jobs that are ready to send.
     */
    private function getPendingJobs(int $limit, int $retryDelay): array
    {
        $now = date('Y-m-d H:i:s');

        $rows = QueryBuilder::select(['*'])
            ->from('email_jobs')
            ->where([
                "status IN ('pending', 'failed')",
                'attempts < max_attempts',
                '(scheduled_at IS NULL OR scheduled_at <= ?)',
                '(last_attempt_at IS NULL OR last_attempt_at <= DATE_SUB(?, INTERVAL ? MINUTE))',
            ], $now, $now, $retryDelay)
            ->orderBy(['scheduled_at ASC'])
            ->limit($limit)
            ->execute()
            ->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($row) => EmailJob::find($row['id']), $rows);
    }

    private function markInProgress(EmailJob $job): void
    {
        $job->status = 'processing';
        $job->save();
    }

    private function markSent(EmailJob $job): void
    {
        $now = date('Y-m-d H:i:s');
        $job->status = 'sent';
        $job->attempts = $job->attempts + 1;
        $job->last_attempt_at = $now;
        $job->sent_at = $now;
        $job->error_message = null;
        $job->save();

        $this->log->info('Email job sent', [
            'job_id' => $job->id,
            'to' => $job->to_address,
            'subject' => $job->subject,
        ]);

        // Audit log
        try {
            AuditLogger::logCreated('email_jobs', $job->id, [
                'event' => 'email_sent',
                'to' => $job->to_address,
                'subject' => $job->subject,
            ]);
        } catch (\Throwable) {
            // Don't let audit failures break email delivery
        }
    }

    private function markFailed(EmailJob $job, string $error): void
    {
        $now = date('Y-m-d H:i:s');
        $attempts = $job->attempts + 1;
        $maxAttempts = $job->max_attempts;

        $job->status = $attempts >= $maxAttempts ? 'exhausted' : 'failed';
        $job->attempts = $attempts;
        $job->last_attempt_at = $now;
        $job->error_message = mb_substr($error, 0, 1000);
        $job->save();

        $this->log->warning('Email job failed', [
            'job_id' => $job->id,
            'to' => $job->to_address,
            'subject' => $job->subject,
            'attempt' => $attempts,
            'max_attempts' => $maxAttempts,
            'status' => $job->status,
            'error' => mb_substr($error, 0, 255),
        ]);

        // Audit log on final failure
        if ($job->status === 'exhausted') {
            try {
                AuditLogger::logCreated('email_jobs', $job->id, [
                    'event' => 'email_exhausted',
                    'to' => $job->to_address,
                    'subject' => $job->subject,
                    'error' => mb_substr($error, 0, 255),
                ]);
            } catch (\Throwable) {
                // Don't let audit failures break processing
            }
        }
    }
}
