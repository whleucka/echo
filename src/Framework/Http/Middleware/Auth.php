<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Framework\Session\Flash;
use Echo\Interface\Http\{Request, Middleware, Response};

/**
 * Authentication (route)
 */
class Auth implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->getAttribute("route");
        $middleware = $route["middleware"];
        $user = user();

        if (in_array('auth', $middleware) && !$user) {
            Flash::add("warning", "Please sign in to view this page.");
            $route = uri("auth.sign-in.index");
            $res = new HttpResponse('', 302);
            $res->setHeader('Location', $route);
            return $res;
        }

        return $next($request);
    }
}
