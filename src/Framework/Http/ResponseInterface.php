<?php

namespace Echo\Framework\Http;

interface ResponseInterface
{
    public function send(): void;
    public function setHeader(string $name, string $value): void;
    public function getStatusCode(): int;
}
