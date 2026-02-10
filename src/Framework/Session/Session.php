<?php

namespace Echo\Framework\Session;

use Echo\Framework\Support\SingletonTrait;

class Session
{
    use SingletonTrait;

    private bool $started = false;
    private array $data = [];
    private static bool $configured = false;

    public function __construct()
    {
        // Configure session settings once
        if (!self::$configured) {
            $this->configure();
            self::$configured = true;
        }
    }

    /**
     * Configure session settings
     */
    private function configure(): void
    {
        // Don't configure if session already started
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // Basic settings
        ini_set('session.gc_maxlifetime', config("session.gc_maxlifetime"));
        ini_set('session.gc_probability', config("session.gc_probability"));
        ini_set('session.gc_divisor', config("session.gc_divisor"));

        // Security settings for session cookies
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);

        // Configure session driver
        $driver = config("session.driver") ?? "file";

        if ($driver === "redis" && $this->isRedisAvailable()) {
            $this->configureRedis();
        } else {
            $this->configureFile();
        }
    }

    /**
     * Configure Redis session handler
     */
    private function configureRedis(): void
    {
        $host = config('redis.default.host') ?? 'redis';
        $port = config('redis.default.port') ?? 6379;
        $database = config('redis.session.database') ?? 2;
        $password = config('redis.default.password');
        $prefix = config('redis.default.prefix') ?? 'echo:';

        // Build Redis save path
        $savePath = "tcp://{$host}:{$port}?database={$database}&prefix={$prefix}session:";

        if (!empty($password)) {
            $savePath .= "&auth={$password}";
        }

        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', $savePath);
    }

    /**
     * Configure file-based session handler
     */
    private function configureFile(): void
    {
        $session_path = config("paths.session");
        if (!is_dir($session_path)) {
            mkdir($session_path, 01733, true);
            @chown($session_path, 'www-data');
            @chgrp($session_path, 'www-data');
        }
        session_save_path($session_path);
    }

    /**
     * Check if Redis is available for sessions
     */
    private function isRedisAvailable(): bool
    {
        // Check if Redis extension is loaded
        if (!extension_loaded('redis')) {
            return false;
        }

        // Try to connect
        try {
            return redis()->isAvailable();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Ensure session is started (lazy initialization)
     */
    private function ensureStarted(): void
    {
        if (!$this->started && session_status() === PHP_SESSION_NONE) {
            @session_start();
            $this->data = $_SESSION ?? [];
            $this->started = true;
        }
    }

    /**
     * Get a session value
     */
    public function get(string $key): mixed
    {
        $this->ensureStarted();
        return $this->data[$key] ?? null;
    }

    /**
     * Set a session key/value
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $this->data[$key] = $value;
        $_SESSION[$key] = $value;
    }

    /**
     * Delete a session key
     */
    public function delete(string $key): void
    {
        $this->ensureStarted();
        unset($this->data[$key]);
        unset($_SESSION[$key]);
    }

    /**
     * Checks existence of session key
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($this->data[$key]);
    }

    /**
     * Get all session variables
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $this->data;
    }

    /**
     * Destroy a session
     */
    public function destroy(): void
    {
        $this->ensureStarted();
        $_SESSION = $this->data = [];
        session_destroy();
        $this->started = false;
    }

    /**
     * Regenerate session ID (prevents session fixation attacks)
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        $this->ensureStarted();
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Get the current session driver
     */
    public function getDriver(): string
    {
        return ini_get('session.save_handler') ?: 'files';
    }

    /**
     * Write session data and close at end of request
     */
    public function __destruct()
    {
        if ($this->started && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
