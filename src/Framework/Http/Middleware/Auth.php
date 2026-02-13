<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;
use Echo\Framework\Session\Flash;

/**
 * Authentication (route)
 */
class Auth implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $route = $request->getAttribute("route");
        $middleware = $route["middleware"] ?? [];

        // Skip auth for benchmark and debug routes (avoids session/Redis overhead)
        if (in_array('benchmark', $middleware, true) || in_array('debug', $middleware, true)) {
            return $next($request);
        }

        $user = user();

        if (in_array('auth', $middleware) && !$user) {
            Flash::add("warning", "Please sign in to view this page.");
            $loginRoute = uri("auth.sign-in.index");

            // HTMX requests get HX-Redirect so the login page
            // doesn't get swapped into a random target element
            if ($request->isHTMX()) {
                $res = new HttpResponse('', 200);
                $res->setHeader('HX-Redirect', $loginRoute);
                return $res;
            }

            $res = new HttpResponse('', 302);
            $res->setHeader('Location', $loginRoute);
            return $res;
        }

        return $next($request);
    }
}
