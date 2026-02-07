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
