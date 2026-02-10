<?php

namespace Echo\Framework\Http;

interface KernelInterface
{
    public function handle(RequestInterface $request): void;
}
