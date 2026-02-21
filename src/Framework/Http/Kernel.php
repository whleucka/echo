<?php

namespace Echo\Framework\Http;

use Echo\Framework\Http\Exception\HttpException;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Framework\Routing\RouterInterface;

use Error;
use Exception;
use PDOException;

class Kernel implements KernelInterface
{
    // Middleware layers
    protected array $middlewareLayers = [];

    public function __construct(
        private RouterInterface $router,
        private ErrorRendererInterface $renderer,
    ) {}

    public function handle(RequestInterface $request): ResponseInterface
    {
        // Dispatch the route
        $route = $this->router->dispatch($request->getUri(), $request->getMethod(), $request->getHost());

        // If there is no route, then 404
        if (is_null($route)) {
            return $this->renderer->renderNotFound($request);
        }

        // Set the current route in the request
        $request->setAttribute('route', $route);

        // Get controller payload through middleware pipeline
        $middleware = new Middleware();
        return $middleware->layer($this->middlewareLayers)
            ->handle($request, fn () => $this->response($route, $request));
    }

    private function response(array $route, RequestInterface $request): ResponseInterface
    {
        // Resolve the controller
        $controller_class = $route['controller'];
        $method = $route['method'];
        $params = $route['params'];
        $middleware = $route['middleware'];
        $api_error = false;
        $controller = null;
        $content = null;

        try {
            // Using the container will allow for DI in the controller constructor
            $controller = container()->get($controller_class);

            // Set the controller request
            $controller->setRequest($request);

            // Set the application user from request attribute (set by Auth middleware)
            $user = $request->getAttribute('user');
            if ($user) {
                $controller->setUser($user);
            }

            // Set the content from the controller endpoint
            profiler()?->startSection('controller');
            $content = $controller->$method(...$params);
            profiler()?->endSection('controller');
        } catch (PDOException $ex) {
            if (in_array('api', $middleware)) {
                $api_error = $this->sanitizeApiError($ex, 'DATABASE_ERROR', 'A database error occurred');
            } else {
                return $this->renderer->renderDatabase($ex, $request);
            }
        } catch (HttpException $ex) {
            if (in_array('api', $middleware)) {
                $api_error = $this->sanitizeApiError($ex, 'HTTP_ERROR', $ex->getMessage());
            } else {
                return $this->renderer->renderHttp($ex, $request);
            }
        } catch (Exception $ex) {
            if (in_array('api', $middleware)) {
                $api_error = $this->sanitizeApiError($ex, 'SERVER_ERROR', 'An error occurred processing your request');
            } else {
                return $this->renderer->renderException($ex, $request);
            }
        } catch (Error $err) {
            if (in_array('api', $middleware)) {
                $api_error = $this->sanitizeApiError($err, 'FATAL_ERROR', 'A fatal error occurred');
            } else {
                return $this->renderer->renderException($err, $request);
            }
        }

        // Check if it is already a response class
        if ($content instanceof HttpResponse) {
            return $content;
        }

        // Create response (api or web)
        if (in_array('api', $middleware)) {
            $code = http_response_code();
            $api_response = [
                'id' => $request->getAttribute('request_id'),
                'success' => $code === 200,
                'status' => $code,
                'data' => $content ?? null,
                'ts' => date(DATE_ATOM),
            ];
            // Handle API errors with sanitized messages
            if ($api_error) {
                $api_response['error'] = $api_error;
                $api_response['success'] = false;
                $api_response['status'] = 500;
                $api_response['data'] = null;
            }
            $response = new JsonResponse($api_response, $api_response['status']);
        } else {
            $response = new HttpResponse($content);
        }

        // Set the headers
        foreach ($controller?->getHeaders() as $key => $value) {
            $response->setHeader($key, $value);
        }

        return $response;
    }

    /**
     * Sanitize error messages for API responses.
     * Only expose details in debug mode, otherwise return generic message.
     */
    private function sanitizeApiError(\Throwable $e, string $code, string $publicMessage): array
    {
        $error = [
            'code' => $code,
            'message' => $publicMessage,
        ];

        if (config('app.debug')) {
            $error['debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        error_log(sprintf(
            "[%s] %s: %s in %s:%d",
            date('Y-m-d H:i:s'),
            $code,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        return $error;
    }
}
