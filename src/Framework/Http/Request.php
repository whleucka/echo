<?php

namespace Echo\Framework\Http;

use Echo\Framework\Http\Request\{Get, Post, Files, Cookie, Headers};
use Echo\Framework\Http\Request\Request as Req;
class Request implements RequestInterface
{
    public Get $get;
    public Post $post;
    public Files $files;
    public Req $request;
    public Cookie $cookie;
    public Headers $headers;

    private array $attributes = [];

    public function __construct(
        array $get = [],
        array $post = [],
        array $request = [],
        array $files = [],
        array $cookie = [],
        array $headers = [],
    ) {
        $this->get = new Get($get);
        $this->post = new Post($post);
        $this->request = new Req($request);
        $this->files = new Files($files);
        $this->cookie = new Cookie($cookie);
        $this->headers = new Headers($headers);
    }

    public function isHTMX(): bool
    {
        if ($this->headers->has("Hx-Request")) {
            return $this->headers->get("Hx-Request");
        }
        return false;
    }

    /**
     * Get the HTMX trigger element ID
     */
    public function getHtmxTrigger(): ?string
    {
        return $this->headers->get("Hx-Trigger") ?: null;
    }

    /**
     * Get the HTMX trigger name (from hx-trigger-name)
     */
    public function getHtmxTriggerName(): ?string
    {
        return $this->headers->get("Hx-Trigger-Name") ?: null;
    }

    /**
     * Get the HTMX target element ID
     */
    public function getHtmxTarget(): ?string
    {
        return $this->headers->get("Hx-Target") ?: null;
    }

    /**
     * Get the current URL that initiated the HTMX request
     */
    public function getHtmxCurrentUrl(): ?string
    {
        return $this->headers->get("Hx-Current-Url") ?: null;
    }

    /**
     * Check if this is a boosted request (hx-boost)
     */
    public function isHtmxBoosted(): bool
    {
        return $this->headers->get("Hx-Boosted") === 'true';
    }

    /**
     * Get the user's response to an hx-prompt
     */
    public function getHtmxPrompt(): ?string
    {
        return $this->headers->get("Hx-Prompt") ?: null;
    }

    /**
     * Check if this is a history restore request
     */
    public function isHtmxHistoryRestore(): bool
    {
        return $this->headers->get("Hx-History-Restore-Request") === 'true';
    }

    public function getHost(): string
    {
        return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    }

    public function getUri(): string
    {
        return strtok($_SERVER["REQUEST_URI"], '?');
    }

    public function getMethod(): string
    {
        return $_SERVER["REQUEST_METHOD"];
    }

    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function getClientIp(array $trustedProxies = []): ?string
    {
        if (php_sapi_name() == "cli") return null;

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

          // Fall back to configured trusted proxies                                                  
        if (empty($trustedProxies)) {                                                               
            $trustedProxies = config('security.trusted_proxies', []);
        }

        // Only trust proxy headers if request comes from a known proxy
        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // Take the first (client) IP from the chain
                $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
                return $ips[0];
            }
            if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                return $_SERVER['HTTP_CLIENT_IP'];
            }
        }

        return $remoteAddr;
    }

    public function curl(string $url, string $method = 'GET', array $headers = [], array|string|null $body = null, int $timeout = 10): array
    {
        $ch = curl_init();

        // Default headers
        $defaultHeaders = [
            'User-Agent: Echo-Request-Agent',
            'Accept: application/json',
        ];

        // Build options
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        ];

        // Attach body for POST, PUT, PATCH
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            if (is_array($body)) {
                $body = http_build_query($body); // form-encoded by default
                $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
            }

            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'error' => $error ?: null,
            'body' => $response,
            'data' => json_decode($response, true),
        ];
    }
}
