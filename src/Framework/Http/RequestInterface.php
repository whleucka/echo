<?php

namespace Echo\Framework\Http;

interface RequestInterface
{
    public function isHTMX(): bool;
    public function getUri(): string;
    public function getMethod(): string;
    public function getHost(): string;
    public function setAttribute(string $name, mixed $value): void;
    public function getAttribute(string $name): mixed;
    public function getAttributes(): array;
    public function getClientIp(): ?string;
    public function curl(string $url, string $method = 'GET', array $headers = [], array|string|null $body = null, int $timeout = 10): array;
}
