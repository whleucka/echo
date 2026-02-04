<?php

namespace Echo\Framework\Logging;

/**
 * PSR-3 Log Level constants
 */
class LogLevel
{
    public const EMERGENCY = 'emergency';
    public const ALERT     = 'alert';
    public const CRITICAL  = 'critical';
    public const ERROR     = 'error';
    public const WARNING   = 'warning';
    public const NOTICE    = 'notice';
    public const INFO      = 'info';
    public const DEBUG     = 'debug';

    /**
     * Log level priorities (lower = more severe)
     */
    public const PRIORITIES = [
        self::EMERGENCY => 0,
        self::ALERT     => 1,
        self::CRITICAL  => 2,
        self::ERROR     => 3,
        self::WARNING   => 4,
        self::NOTICE    => 5,
        self::INFO      => 6,
        self::DEBUG     => 7,
    ];

    /**
     * Check if a level should be logged based on minimum level
     */
    public static function shouldLog(string $level, string $minLevel): bool
    {
        $levelPriority = self::PRIORITIES[$level] ?? 7;
        $minPriority = self::PRIORITIES[$minLevel] ?? 7;
        return $levelPriority <= $minPriority;
    }
}
