<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * Whitelist
 */
class Whitelist implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $whitelist = config("security.whitelist");
        $ip = $request->getClientIp();

        if (!empty($whitelist) && !in_array($ip, $whitelist)) {
            logger()->channel('auth')->warning('Whitelist denied: IP not in allowlist', [
                'ip' => $ip,
                'path' => $request->getUri(),
            ]);
            return new HttpResponse("Access denied", 403);
        }

        return $next($request);
    }
}
