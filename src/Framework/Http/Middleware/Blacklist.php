<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * Blacklist
 */
class Blacklist implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $blacklist = config("security.blacklist");
        $ip = $request->getClientIp();

        if (in_array($ip, $blacklist)) {
            return new HttpResponse("Access denied", 403);
        }

        return $next($request);
    }
}
