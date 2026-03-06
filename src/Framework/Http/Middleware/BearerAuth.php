<?php

namespace Echo\Framework\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use Closure;
use Echo\Framework\Http\JsonResponse;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * Bearer Token Authentication Middleware
 * Validates API tokens for stateless authentication
 */
class BearerAuth implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $route = $request->getAttribute("route");
        $middleware = $route["middleware"] ?? [];

        // Only apply to routes with 'api' or 'bearer' middleware
        if (!in_array('api', $middleware) && !in_array('bearer', $middleware)) {
            return $next($request);
        }

        // Skip if already authenticated via session
        if (session()->get("user_uuid")) {
            return $next($request);
        }

        // Check for Authorization header
        $authHeader = $this->getAuthorizationHeader($request);

        if (!$authHeader) {
            // No auth header - let other middleware handle or proceed
            // This allows session auth to work alongside bearer auth
            return $next($request);
        }

        // Parse Bearer token
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Invalid authorization header format');
        }

        $token = $matches[1];

        // Validate token
        $apiToken = $this->validateToken($token);

        if (!$apiToken) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        // Set token info on request attributes (stateless — no session mutation)
        $request->setAttribute('api_token', $apiToken);
        $request->setAttribute('api_user_id', $apiToken->user_id);

        // Resolve and set the user as a request attribute so the Kernel can
        // call setUser() on the controller — same path as session-based Auth.
        $user = User::find($apiToken->user_id);
        if ($user) {
            $request->setAttribute('user', $user);
        }

        return $next($request);
    }

    /**
     * Get Authorization header from request.
     *
     * Uses the request headers object (populated via getallheaders() at boot).
     * Falls back to $_SERVER for Apache mod_rewrite edge cases where
     * REDIRECT_HTTP_AUTHORIZATION is set but not in getallheaders().
     */
    private function getAuthorizationHeader(RequestInterface $request): ?string
    {
        // Primary: read from request headers (covers standard + getallheaders)
        $header = $request->headers->get('Authorization');
        if ($header) {
            return $header;
        }

        // Fallback: Apache mod_rewrite sets REDIRECT_HTTP_AUTHORIZATION
        // which may not appear in getallheaders()
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return null;
    }

    /**
     * Validate API token
     */
    private function validateToken(string $token): ?object
    {
        // Hash the token for comparison (tokens are stored hashed)
        $hashedToken = hash('sha256', $token);

        try {
            $apiToken = ApiToken::where('token', $hashedToken)
                ->andWhere('revoked', '0')
                ->first();

            if (!$apiToken) {
                return null;
            }

            // Check expiration
            if ($apiToken->expires_at && strtotime($apiToken->expires_at) < time()) {
                return null;
            }

            // Update last used timestamp
            $apiToken->last_used_at = date('Y-m-d H:i:s');
            $apiToken->save();

            return $apiToken;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Return 401 unauthorized JSON response
     */
    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new JsonResponse([
            'id' => request()->getAttribute('request_id'),
            'success' => false,
            'status' => 401,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message,
            ],
            'ts' => date(DATE_ATOM),
        ], 401);

        return $response;
    }
}
