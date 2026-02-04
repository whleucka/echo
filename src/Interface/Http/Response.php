<?php

namespace Echo\Interface\Http;

interface Response
{
    public function send(): void;
    public function setHeader(string $name, string $value): void;
    public function getStatusCode(): int;
}
