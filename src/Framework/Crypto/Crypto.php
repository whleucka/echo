<?php

namespace Echo\Framework\Crypto;

/**
 * Cryptographic utilities backed by APP_KEY.
 *
 * Provides HMAC signing, symmetric encryption, and secure random generation.
 * All methods require APP_KEY to be set in .env.
 */
class Crypto
{
    private string $key;

    public function __construct(?string $key = null)
    {
        $this->key = $key ?? config('app.key') ?? '';

        if (empty($this->key)) {
            throw new \RuntimeException(
                'APP_KEY is not set. Generate one with: php -r "echo \'APP_KEY=\' . bin2hex(random_bytes(32)) . PHP_EOL;"'
            );
        }
    }

    /**
     * Create an HMAC signature for data.
     */
    public function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->key);
    }

    /**
     * Verify an HMAC signature (timing-safe).
     */
    public function verify(string $data, string $signature): bool
    {
        return hash_equals($this->sign($data), $signature);
    }

    /**
     * Encrypt data using AES-256-GCM.
     *
     * Returns base64-encoded string containing nonce + ciphertext + tag.
     */
    public function encrypt(string $plaintext): string
    {
        $key = hash('sha256', $this->key, true);
        $nonce = random_bytes(12); // 96-bit nonce for GCM

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Pack: nonce (12) + tag (16) + ciphertext
        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt data encrypted with encrypt().
     *
     * @return string|false Plaintext on success, false on failure
     */
    public function decrypt(string $encoded): string|false
    {
        $key = hash('sha256', $this->key, true);
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < 28) {
            return false;
        }

        $nonce = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        return $plaintext;
    }

    /**
     * Generate a URL-safe random token.
     */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Get the raw key (for derived use only -- do not expose).
     */
    public function getKey(): string
    {
        return $this->key;
    }
}
