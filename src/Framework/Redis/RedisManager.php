<?php

namespace Echo\Framework\Redis;

use Redis;
use RedisException;
use RuntimeException;

/**
 * Redis Manager
 *
 * Manages Redis connections with support for multiple databases.
 * Provides lazy connection initialization and connection pooling.
 */
class RedisManager
{
    private static ?RedisManager $instance = null;
    private array $connections = [];
    private array $config;

    private function __construct()
    {
        $this->config = config('redis');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get a Redis connection for the specified purpose
     */
    public function connection(string $name = 'default'): Redis
    {
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }
        return $this->connections[$name];
    }

    /**
     * Create a new Redis connection
     */
    private function createConnection(string $name): Redis
    {
        $default = $this->config['default'];
        $specific = $this->config[$name] ?? [];

        $host = $specific['host'] ?? $default['host'];
        $port = $specific['port'] ?? $default['port'];
        $password = $specific['password'] ?? $default['password'];
        $database = $specific['database'] ?? $default['database'];

        $redis = new Redis();

        try {
            // Suppress PHP warning on connection failure (we handle it via exception)
            $connected = @$redis->connect($host, $port, 2.0); // 2 second timeout
            if (!$connected) {
                throw new RuntimeException("Failed to connect to Redis at {$host}:{$port}");
            }

            if (!empty($password)) {
                $redis->auth($password);
            }

            $redis->select($database);

            // Set prefix for this connection
            $prefix = $default['prefix'] ?? 'echo:';
            $redis->setOption(Redis::OPT_PREFIX, $prefix . $name . ':');

        } catch (\RedisException $e) {
            throw new RuntimeException("Redis connection failed: " . $e->getMessage(), 0, $e);
        }

        return $redis;
    }

    /**
     * Check if Redis is available
     */
    public function isAvailable(): bool
    {
        try {
            $redis = $this->connection('default');
            return $redis->ping() === true || $redis->ping() === '+PONG';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Close all connections
     */
    public function disconnect(): void
    {
        foreach ($this->connections as $connection) {
            try {
                $connection->close();
            } catch (\Throwable) {
                // Ignore close errors
            }
        }
        $this->connections = [];
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}
}
