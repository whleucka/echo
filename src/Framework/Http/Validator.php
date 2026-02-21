<?php

namespace Echo\Framework\Http;

class Validator
{
    private array $errors = [];
    private array $messages = [
        "required" => "Required field",
        "unique" => "Must be unique",
        "string" => "Must be a string",
        "array" => "Must be an array",
        "date" => "Invalid date format",
        "numeric" => "Must be a numeric value",
        "email" => "Invalid email address",
        "integer" => "Must be an integer",
        "float" => "Must be a floating-point number",
        "boolean" => "Must be a boolean value",
        "url" => "Invalid URL format",
        "ip" => "Invalid IP address",
        "ipv4" => "Must be a valid IPv4 address",
        "ipv6" => "Must be a valid IPv6 address",
        "mac" => "Invalid MAC address",
        "domain" => "Invalid domain name",
        "uuid" => "Invalid UUID format",
        "match" => "Does not match",
        "min_length" => "Input is too short",
        "max_length" => "Input is too long",
        "regex" => "Does not match pattern",
    ];

    public function setMessage(string $rule, string $message): void
    {
        $this->messages[$rule] = $message;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function run(array $ruleset, mixed $_id, array $request): mixed
    {
        $valid = true;
        $data  = [];

        foreach ($ruleset as $field => $set) {
            if (empty($set)) {
                $data[$field] = $request[$field] ?? null;
                continue;
            }
            $isRequired = in_array('required', $set);
            $fieldValue = $request[$field] ?? null;
            if (!$isRequired && ($fieldValue === null || $fieldValue === '')) {
                $data[$field] = $fieldValue;
                continue;
            }
            $fieldValid = true;
            foreach ($set as $rule) {
                $r        = explode(":", $rule, 2);
                $ruleName = $r[0];
                $ruleVal  = $r[1] ?? null;
                $requestValue = $request[$field] ?? null;

                if (!$this->applyRule($ruleName, $ruleVal, $requestValue, $request, $field)) {
                    if (isset($this->messages[$field . '.' . $ruleName])) {
                        $this->addError($field, $this->messages[$field . '.' . $ruleName]);
                    } elseif (isset($this->messages[$ruleName])) {
                        $this->addError($field, $this->messages[$ruleName]);
                    } else {
                        $this->addError($field, "Invalid");
                    }
                    $fieldValid = false;
                    break;
                }
            }
            if ($fieldValid) {
                $data[$field] = $request[$field];
            } else {
                $valid = false;
            }
        }

        return $valid ? (object) $data : null;
    }

    private function applyRule(
        string $ruleName,
        ?string $ruleVal,
        mixed $fieldValue,
        array $request,
        string $fieldName
    ): bool {
        return match ($ruleName) {
            'match'      => $fieldValue == $request[$ruleVal],
            'unique'     => $this->validateUnique($ruleVal, $fieldName, $fieldValue),
            'min_length' => mb_strlen((string) $fieldValue) >= (int) $ruleVal,
            'max_length' => mb_strlen((string) $fieldValue) <= (int) $ruleVal,
            'required'   => !is_null($fieldValue) && $fieldValue !== '' && $fieldValue !== "NULL",
            'string'     => is_string($fieldValue),
            'array'      => is_array($fieldValue),
            'date'       => strtotime($fieldValue) !== false,
            'numeric'    => is_numeric($fieldValue),
            'email'      => filter_var($fieldValue, FILTER_VALIDATE_EMAIL) !== false,
            'integer'    => filter_var($fieldValue, FILTER_VALIDATE_INT) !== false,
            'float'      => filter_var($fieldValue, FILTER_VALIDATE_FLOAT) !== false,
            'boolean'    => filter_var($fieldValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,
            'url'        => filter_var($fieldValue, FILTER_VALIDATE_URL) !== false,
            'ip'         => filter_var($fieldValue, FILTER_VALIDATE_IP) !== false,
            'ipv4'       => filter_var($fieldValue, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'ipv6'       => filter_var($fieldValue, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'mac'        => filter_var($fieldValue, FILTER_VALIDATE_MAC) !== false,
            'domain'     => filter_var($fieldValue, FILTER_VALIDATE_DOMAIN) !== false,
            'uuid'       => (bool) preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $fieldValue
            ),
            'regex'      => (bool) preg_match(
                '~' . str_replace('~', '\~', $ruleVal) . '~',
                (string) $fieldValue
            ),
            default      => throw new \Error("undefined validation rule: $ruleName"),
        };
    }

    /**
     * Validate uniqueness with SQL injection protection
     */
    private function validateUnique(string $table, string $field, mixed $value): bool
    {
        // Sanitize table and field names - only allow alphanumeric and underscores
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

        if ($safeTable !== $table || $safeField !== $field) {
            throw new \InvalidArgumentException("Invalid table or field name for unique validation");
        }

        return count(db()->fetch("SELECT 1 FROM `$safeTable` WHERE `$safeField` = ?", [$value])) === 0;
    }
}
