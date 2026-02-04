<?php

namespace Echo\Framework\Audit;

use App\Models\Audit;

class AuditLogger
{
    private static ?int $userId = null;
    private static ?string $ipAddress = null;
    private static ?string $userAgent = null;

    /**
     * Sensitive fields that should never be logged
     */
    private static array $sensitiveFields = [
        'password',
        'password_hash',
        'password_match',
        'token',
        'secret',
        'api_key',
        'api_secret',
        'access_token',
        'refresh_token',
        'private_key',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ];

    /**
     * Set the current context for audit logging
     */
    public static function setContext(?int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        self::$userId = $userId;
        self::$ipAddress = $ipAddress;
        self::$userAgent = $userAgent;
    }

    /**
     * Get the current user ID
     */
    public static function getUserId(): ?int
    {
        return self::$userId;
    }

    /**
     * Get the current IP address
     */
    public static function getIpAddress(): ?string
    {
        return self::$ipAddress;
    }

    /**
     * Get the current user agent
     */
    public static function getUserAgent(): ?string
    {
        return self::$userAgent;
    }

    /**
     * Log a model creation event
     */
    public static function logCreated(string $modelClass, int|string $modelId, array $newValues): void
    {
        self::log($modelClass, $modelId, 'created', [], $newValues);
    }

    /**
     * Log a model update event
     */
    public static function logUpdated(string $modelClass, int|string $modelId, array $oldValues, array $newValues): void
    {
        self::log($modelClass, $modelId, 'updated', $oldValues, $newValues);
    }

    /**
     * Log a model deletion event
     */
    public static function logDeleted(string $modelClass, int|string $modelId, array $oldValues): void
    {
        self::log($modelClass, $modelId, 'deleted', $oldValues, []);
    }

    /**
     * Create an audit log entry
     */
    private static function log(
        string $modelClass,
        int|string $modelId,
        string $event,
        array $oldValues,
        array $newValues
    ): void {
        $filteredOld = self::filterSensitiveData($oldValues);
        $filteredNew = self::filterSensitiveData($newValues);

        Audit::create([
            'user_id' => self::$userId,
            'auditable_type' => $modelClass,
            'auditable_id' => (int) $modelId,
            'event' => $event,
            'old_values' => !empty($filteredOld) ? json_encode($filteredOld) : null,
            'new_values' => !empty($filteredNew) ? json_encode($filteredNew) : null,
            'ip_address' => self::$ipAddress,
            'user_agent' => self::$userAgent,
        ]);
    }

    /**
     * Filter out sensitive fields from data
     */
    private static function filterSensitiveData(array $data): array
    {
        $filtered = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;

            foreach (self::$sensitiveFields as $sensitiveField) {
                if (str_contains($lowerKey, $sensitiveField)) {
                    $isSensitive = true;
                    break;
                }
            }

            if (!$isSensitive) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    /**
     * Add a custom sensitive field pattern
     */
    public static function addSensitiveField(string $field): void
    {
        if (!in_array($field, self::$sensitiveFields)) {
            self::$sensitiveFields[] = $field;
        }
    }

    /**
     * Get the list of sensitive fields
     */
    public static function getSensitiveFields(): array
    {
        return self::$sensitiveFields;
    }
}
