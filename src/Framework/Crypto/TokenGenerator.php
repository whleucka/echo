<?php

namespace Echo\Framework\Crypto;

/**
 * Generates and validates time-limited, HMAC-signed tokens.
 *
 * Used for password reset, email verification, 2FA backup codes, etc.
 *
 * Token format: base64(payload.expiry.signature)
 * where signature = HMAC-SHA256(payload.expiry, APP_KEY)
 */
class TokenGenerator
{
    private Crypto $crypto;

    public function __construct(?Crypto $crypto = null)
    {
        $this->crypto = $crypto ?? crypto();
    }

    /**
     * Generate a signed, time-limited token.
     *
     * @param string $payload Arbitrary data to embed (e.g. user ID, email)
     * @param int $ttlMinutes Token lifetime in minutes
     * @return string URL-safe base64 token
     */
    public function generate(string $payload, int $ttlMinutes = 60): string
    {
        $expiry = time() + ($ttlMinutes * 60);
        $data = $payload . '.' . $expiry;
        $signature = $this->crypto->sign($data);

        return $this->base64UrlEncode($data . '.' . $signature);
    }

    /**
     * Validate a token and return its payload if valid.
     *
     * @return string|false The original payload, or false if invalid/expired
     */
    public function validate(string $token): string|false
    {
        $decoded = $this->base64UrlDecode($token);
        if ($decoded === false) {
            return false;
        }

        // Split into payload.expiry.signature
        $parts = explode('.', $decoded);
        if (count($parts) < 3) {
            return false;
        }

        $signature = array_pop($parts);
        $expiry = array_pop($parts);
        $payload = implode('.', $parts); // payload may contain dots

        // Verify signature
        $data = $payload . '.' . $expiry;
        if (!$this->crypto->verify($data, $signature)) {
            return false;
        }

        // Check expiry
        if (!is_numeric($expiry) || time() > (int) $expiry) {
            return false;
        }

        return $payload;
    }

    /**
     * Generate a simple random token (no signing, no expiry).
     * Useful for one-time-use database-stored tokens.
     */
    public function random(int $bytes = 32): string
    {
        return Crypto::randomToken($bytes);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
