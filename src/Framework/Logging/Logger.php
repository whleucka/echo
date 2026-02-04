<?php

namespace Echo\Framework\Logging;

use Echo\Traits\Creational\Singleton;

/**
 * PSR-3 compatible file logger with daily rotation
 */
class Logger
{
    use Singleton;

    private string $logPath;
    private string $channel;
    private string $minLevel;
    private bool $jsonFormat;

    public function __construct(
        ?string $channel = null,
        ?string $logPath = null,
        ?string $minLevel = null,
        ?bool $jsonFormat = null
    ) {
        $this->channel = $channel ?? config('debug.log_channel') ?? 'app';
        $this->logPath = $logPath ?? config('paths.root') . 'storage/logs/';
        $this->minLevel = $minLevel ?? config('debug.log_level') ?? LogLevel::DEBUG;
        $this->jsonFormat = $jsonFormat ?? config('debug.log_json') ?? false;

        $this->ensureLogDirectory();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create a new logger with a specific channel
     */
    public function channel(string $channel): self
    {
        return new self($channel, $this->logPath, $this->minLevel, $this->jsonFormat);
    }

    /**
     * System is unusable
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log with an arbitrary level
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!LogLevel::shouldLog($level, $this->minLevel)) {
            return;
        }

        $interpolated = $this->interpolate($message, $context);
        $entry = $this->formatEntry($level, $interpolated, $context);

        $this->write($entry);
    }

    /**
     * Interpolate context values into placeholders
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (!is_array($value) && (!is_object($value) || method_exists($value, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }
        return strtr($message, $replace);
    }

    /**
     * Format a log entry
     */
    private function formatEntry(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');

        if ($this->jsonFormat) {
            return json_encode([
                'timestamp' => $timestamp,
                'channel' => $this->channel,
                'level' => $level,
                'message' => $message,
                'context' => $context ?: new \stdClass(),
            ]) . PHP_EOL;
        }

        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        return sprintf(
            "[%s] %s.%s: %s%s\n",
            $timestamp,
            $this->channel,
            strtoupper($level),
            $message,
            $contextStr
        );
    }

    /**
     * Write entry to log file
     */
    private function write(string $entry): void
    {
        $filename = sprintf('%s-%s.log', $this->channel, date('Y-m-d'));
        $filepath = $this->logPath . $filename;

        file_put_contents($filepath, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Get the current log file path
     */
    public function getLogFile(): string
    {
        return $this->logPath . sprintf('%s-%s.log', $this->channel, date('Y-m-d'));
    }
}
