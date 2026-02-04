<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class ApiToken extends Model
{
    protected string $table_name = 'api_tokens';

    /**
     * Generate a new API token for a user
     */
    public static function generate(int $userId, ?string $name = null, ?int $expiresInDays = null): array
    {
        // Generate a secure random token
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        $expiresAt = null;
        if ($expiresInDays) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));
        }

        $token = self::create([
            'user_id' => $userId,
            'name' => $name ?? 'API Token',
            'token' => $hashedToken,
            'expires_at' => $expiresAt,
            'revoked' => 0,
        ]);

        // Return plain token only once - it cannot be retrieved later
        return [
            'token' => $token,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Revoke this token
     */
    public function revoke(): void
    {
        $this->revoked = 1;
        $this->save();
    }

    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        if ($this->revoked) {
            return false;
        }

        if ($this->expires_at && strtotime($this->expires_at) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Get user that owns this token
     */
    public function user(): ?Model
    {
        return $this->belongsTo(User::class);
    }
}
