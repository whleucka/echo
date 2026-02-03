<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Interface\Http\{Request, Middleware, Response};

/**
 * CSRF
 */
class CSRF implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->getAttribute("route");
        $middleware = $route["middleware"];
        $this->setup();

        if (!in_array('api', $middleware) && !$this->validate($request)) {
            return new HttpResponse("Invalid CSRF token", 403);
        }

        return $next($request);
    }

    /**
     * Setup CSRF token
     */
    private function setup(): void
    {
        $token = session()->get("csrf_token");
        $token_ts = session()->get("csrf_token_ts");

        if (
            is_null($token) ||
            is_null($token_ts) ||
            $token_ts + 3600 < time()
        ) {
            $token = $this->generateToken();
            session()->set("csrf_token", $token);
            session()->set("csrf_token_ts", time());
        }
    }

    /**
     * Generate a CSRF token string
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate a CSRF request token
     */
    private function validate(Request $request): bool
    {
        $request_method = $request->getMethod();
        if (in_array($request_method, ["GET", "HEAD", "OPTIONS"])) {
            return true;
        }

        $session_token = session()->get("csrf_token");
        $request_token = $request->post->csrf_token;

        if (
            !is_null($session_token) &&
            !is_null($request_token) &&
            hash_equals($session_token, $request_token)
        ) {
            return true;
        }

        return false;
    }
}
