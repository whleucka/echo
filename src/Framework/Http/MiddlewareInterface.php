<?php

namespace Echo\Framework\Http;

use Closure;

interface MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface;
}
