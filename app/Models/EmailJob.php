<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class EmailJob extends Model
{
    public function __construct(?string $id = null)
    {
        parent::__construct('email_jobs', $id);
    }

    /**
     * Check if this job can be retried
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts
            && $this->status !== 'sent'
            && $this->status !== 'exhausted';
    }

    /**
     * Check if this job has been sent
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Get the decoded payload
     */
    public function getPayload(): array
    {
        if (is_string($this->payload)) {
            return json_decode($this->payload, true) ?? [];
        }
        return $this->payload ?? [];
    }
}
