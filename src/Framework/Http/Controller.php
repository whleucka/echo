<?php

namespace Echo\Framework\Http;

use App\Models\User;
use Echo\Framework\Http\Exception\HttpForbiddenException;
use Echo\Framework\Http\Exception\HttpNotFoundException;
use Echo\Framework\Session\Flash;
use Error;

class Controller implements ControllerInterface
{
    protected ?User $user = null;
    protected ?RequestInterface $request = null;
    private array $headers = [];
    private array $validationErrors = [];
    private array $validationMessages = [
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

    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * HTMX Trigger
     */
    public function hxTrigger(array|string $opts): void
    {
        if (is_array($opts)) {
            $opts = json_encode($opts);
        }
        $this->setHeader("HX-Trigger", $opts);
    }

    public function validate(array $ruleset = [], mixed $id = null): mixed
    {
        $valid = true;

        $req  = (array) ($this->request->request->data() ?? []);
        $body = (array) json_decode(file_get_contents('php://input') ?: '{}', true);
        $request = [...$req, ...$body];

        $data = [];

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
            foreach ($set as $rule) {
                $r = explode(":", $rule);
                $rule = $r[0];
                $rule_val = $r[1] ?? null;
                $request_value = $request[$field] ?? null;
                $result = match($rule) {
                    'match' => $request_value == $request[$rule_val],
                    'unique' => $this->validateUnique($rule_val, $field, $request_value),
                    'min_length' => strlen($request_value) >= $rule_val,
                    'max_length' => strlen($request_value) <= $rule_val,
                    'required' => !is_null($request_value) && $request_value !== '' && $request_value !== "NULL",
                    'string' => is_string($request_value),
                    'array' => is_array($request_value),
                    'date' => strtotime($request_value) !== false,
                    'numeric' => is_numeric($request_value),
                    'email' => filter_var($request_value, FILTER_VALIDATE_EMAIL) !== false,
                    'integer' => filter_var($request_value, FILTER_VALIDATE_INT) !== false,
                    'float' => filter_var($request_value, FILTER_VALIDATE_FLOAT) !== false,
                    'boolean' => filter_var($request_value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,
                    'url' => filter_var($request_value, FILTER_VALIDATE_URL) !== false,
                    'ip' => filter_var($request_value, FILTER_VALIDATE_IP) !== false,
                    'ipv4' => filter_var($request_value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
                    'ipv6' => filter_var($request_value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
                    'mac' => filter_var($request_value, FILTER_VALIDATE_MAC) !== false,
                    'domain' => filter_var($request_value, FILTER_VALIDATE_DOMAIN) !== false,
                    'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $request_value),
                    'regex' => preg_match("/$rule_val/", $request_value),
                    default => throw new \Error("undefined validation rule: $rule")
                };
                if ($result) {
                    $data[$field] = $request[$field];
                } else {
                    if (isset($this->validationMessages[$field.'.'.$rule])) {
                        $this->addValidationError($field, $this->validationMessages[$field.'.'.$rule]);
                    } else if (isset($this->validationMessages[$rule])) {
                        $this->addValidationError($field, $this->validationMessages[$rule]);
                    } else {
                        $this->addValidationError($field, "Invalid");
                    }
                }
                $valid &= $result;
            }
        }
        return $valid ? (object)$data : null;
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

    protected function setValidationMessage(string $rule, string $message)
    {
        $this->validationMessages[$rule] = $message;
    }

    protected function addValidationError(string $field, string $message)
    {
        $this->validationErrors[$field][] = $message;
    }

    protected function getDefaultTemplateData(): array
    {
        return [
            "app" => config("app"),
            "flash" => Flash::get(),
            "validationErrors" => $this->validationErrors,
        ];
    }

    public function pageNotFound(): never
    {
        throw new HttpNotFoundException();
    }

    public function permissionDenied(): never
    {
        throw new HttpForbiddenException();
    }

    protected function render(string $template, array $data = []): string
    {
        $twig = twig();
        $data = array_merge($data, $this->getDefaultTemplateData());
        return $twig->render($template, $data);
    }
}
