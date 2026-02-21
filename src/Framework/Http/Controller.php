<?php

namespace Echo\Framework\Http;

use App\Models\User;
use Echo\Framework\Http\Exception\HttpForbiddenException;
use Echo\Framework\Http\Exception\HttpNotFoundException;
use Echo\Framework\Session\Flash;

class Controller implements ControllerInterface
{
    protected ?User $user = null;
    protected ?RequestInterface $request = null;
    private array $headers = [];
    private array $validationErrors = [];
    private ?array $jsonBody = null;
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

    private function getJsonBody(): array
    {
        if ($this->jsonBody === null) {
            $this->jsonBody = (array) json_decode(
                file_get_contents('php://input') ?: '{}',
                true
            );
        }
        return $this->jsonBody;
    }

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

    public function getRequest(): ?RequestInterface
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
        $request = [...$req, ...$this->getJsonBody()];

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
            $fieldValid = true;
            foreach ($set as $rule) {
                $r        = explode(":", $rule, 2);
                $ruleName = $r[0];
                $ruleVal  = $r[1] ?? null;
                $requestValue = $request[$field] ?? null;
                $result = match($ruleName) {
                    'match' => $requestValue == $request[$ruleVal],
                    'unique' => $this->validateUnique($ruleVal, $field, $requestValue),
                    'min_length' => mb_strlen((string) $requestValue) >= (int) $ruleVal,
                    'max_length' => mb_strlen((string) $requestValue) <= (int) $ruleVal,
                    'required' => !is_null($requestValue) && $requestValue !== '' && $requestValue !== "NULL",
                    'string' => is_string($requestValue),
                    'array' => is_array($requestValue),
                    'date' => strtotime($requestValue) !== false,
                    'numeric' => is_numeric($requestValue),
                    'email' => filter_var($requestValue, FILTER_VALIDATE_EMAIL) !== false,
                    'integer' => filter_var($requestValue, FILTER_VALIDATE_INT) !== false,
                    'float' => filter_var($requestValue, FILTER_VALIDATE_FLOAT) !== false,
                    'boolean' => filter_var($requestValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,
                    'url' => filter_var($requestValue, FILTER_VALIDATE_URL) !== false,
                    'ip' => filter_var($requestValue, FILTER_VALIDATE_IP) !== false,
                    'ipv4' => filter_var($requestValue, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
                    'ipv6' => filter_var($requestValue, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
                    'mac' => filter_var($requestValue, FILTER_VALIDATE_MAC) !== false,
                    'domain' => filter_var($requestValue, FILTER_VALIDATE_DOMAIN) !== false,
                    'uuid' => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $requestValue),
                    'regex' => (bool) preg_match('~' . str_replace('~', '\~', $ruleVal) . '~', (string) $requestValue),
                    default => throw new \Error("undefined validation rule: $ruleName")
                };

                if (!$result) {
                    if (isset($this->validationMessages[$field . '.' . $ruleName])) {
                        $this->addValidationError($field, $this->validationMessages[$field . '.' . $ruleName]);
                    } elseif (isset($this->validationMessages[$ruleName])) {
                        $this->addValidationError($field, $this->validationMessages[$ruleName]);
                    } else {
                        $this->addValidationError($field, "Invalid");
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

    protected function setValidationMessage(string $rule, string $message): void
    {
        $this->validationMessages[$rule] = $message;
    }

    protected function addValidationError(string $field, string $message): void
    {
        $this->validationErrors[$field][] = $message;
    }

    protected function getDefaultTemplateData(): array
    {
        return [
            "app" => config("app"),
            "framework" => config("framework"),
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
        $data = array_merge($this->getDefaultTemplateData(), $data);
        return $twig->render($template, $data);
    }
}
