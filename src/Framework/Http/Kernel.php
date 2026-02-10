<?php

namespace Echo\Framework\Http;

use App\Models\User;
use chillerlan\QRCode\QRCode;
use Echo\Framework\Http\Exception\HttpException;
use Echo\Framework\Http\Response as HttpResponse;

use Error;
use Exception;
use PDOException;

class Kernel implements KernelInterface
{
    // Middleware layers
    protected array $middleware_layers = [];

    public function handle(RequestInterface $request): void
    {
        // Dispatch the route
        $route = router()->dispatch($request->getUri(), $request->getMethod());

        // If there is no route, then 404
        if (is_null($route)) {
            $content = twig()->render("error/404.html.twig");
            $response = new HttpResponse($content, 404);
            $response->send();
            exit;
        }

        // Set the current route in the request
        $request->setAttribute("route", $route);

        // Get controller payload
        $middleware = new Middleware();
        $response = $middleware->layer($this->middleware_layers)
            ->handle($request, fn () => $this->response($route, $request));

        $response->send();
        exit;
    }

    private function response(array $route, RequestInterface $request): ResponseInterface
    {
        // Resolve the controller
        $controller_class = $route['controller'];
        $method = $route['method'];
        $params = $route['params'];
        $middleware = $route['middleware'];
        $api_error = false;
        $request_id = $request->getAttribute("request_id");

        try {
            // Using the container will allow for DI
            // in the controller constructor
            $controller = container()->get($controller_class);

            // Set the controller request
            $controller->setRequest($request);

            // Set the application user
            $uuid = session()->get("user_uuid");
            if ($uuid) {
                $user = User::where("uuid", $uuid)->get();
                $controller->setUser($user);
            }

            // Set the content from the controller endpoint
            profiler()?->startSection('controller');
            $content = $controller->$method(...$params);
            profiler()?->endSection('controller');
        } catch (PDOException $ex) {
            // Handle database exception
            if (in_array("api", $middleware)) {
                $api_error = $this->sanitizeApiError($ex, 'DATABASE_ERROR', 'A database error occurred');
            } else {
                $debug = db()->debug();
                $extra = "<strong>SQL</strong><pre>" . $debug['sql'] . "</pre>";
                $extra .= "<strong>Params</strong><pre>" . print_r($debug['params'], true) . "</pre>";
                $content = twig()->render("error/blue-screen.html.twig", [
                    "message" => "An database error has occurred.",
                    "extra" => $extra,
                    "debug" => config("app.debug"),
                    "request_id" => $request_id,
                    "e" => $ex,
                    "qr" => (new QRCode)->render($request_id),
                    "is_logged" => session()->get("user_uuid"),
                ]);
                $response = new HttpResponse($content, 500);
                return $response;
            }
        } catch (HttpException $ex) {
            // Handle HTTP exceptions (404, 403, etc.)
            if (in_array("api", $middleware)) {
                $api_error = $this->sanitizeApiError($ex, 'HTTP_ERROR', $ex->getMessage());
            } else {
                $template = match ($ex->statusCode) {
                    404 => "error/404.html.twig",
                    403 => "error/permission-denied.html.twig",
                    default => "error/blue-screen.html.twig",
                };
                $content = twig()->render($template, [
                    "message" => $ex->getMessage(),
                    "debug" => config("app.debug"),
                    "request_id" => $request_id,
                    "e" => $ex,
                    "is_logged" => session()->get("user_uuid"),
                ]);
                return new HttpResponse($content, $ex->statusCode);
            }
        } catch (Exception $ex) {
            // Handle exception
            if (in_array("api", $middleware)) {
                $api_error = $this->sanitizeApiError($ex, 'SERVER_ERROR', 'An error occurred processing your request');
            } else {
                $content = twig()->render("error/blue-screen.html.twig", [
                    "message" => "An uncaught exception occurred.",
                    "debug" => config("app.debug"),
                    "request_id" => $request_id,
                    "e" => $ex,
                    "qr" => (new QRCode)->render($request_id),
                    "is_logged" => session()->get("user_uuid"),
                ]);
                $response = new HttpResponse($content, 500);
                return $response;
            }
        } catch (Error $err) {
            // Handle error
            if (in_array("api", $middleware)) {
                $api_error = $this->sanitizeApiError($err, 'FATAL_ERROR', 'A fatal error occurred');
            } else {
                $content = twig()->render("error/blue-screen.html.twig", [
                    "message" => "A fatal error occurred.",
                    "debug" => config("app.debug"),
                    "request_id" => $request_id,
                    "e" => $err,
                    "qr" => (new QRCode)->render($request_id),
                    "is_logged" => session()->get("user_uuid"),
                ]);
                $response = new HttpResponse($content, 500);
                return $response;
            }
        }

        // Create response (api or web)
        if (in_array("api", $middleware)) {
            $code = http_response_code();
            $api_response = [
                "id" => $request->getAttribute("request_id"),
                "success" => $code === 200,
                "status" => $code,
                "data" => $content ?? null,
                "ts" => date(DATE_ATOM),
            ];
            // Handle API errors with sanitized messages
            if ($api_error) {
                $api_response["error"] = $api_error;
                $api_response["success"] = false;
                $api_response["status"] = 500;
                $api_response["data"] = null;
            }
            // API response
            $response = new JsonResponse($api_response, $api_response["status"]);
        } else {
            // Web response
            $response = new HttpResponse($content);
        }

        // Set the headers
        foreach ($controller?->getHeaders() as $key => $value) {
            $response->setHeader($key, $value);
        }

        return $response;
    }

    /**
     * Sanitize error messages for API responses
     * Only expose details in debug mode, otherwise return generic message
     */
    private function sanitizeApiError(\Throwable $e, string $code, string $publicMessage): array
    {
        $error = [
            'code' => $code,
            'message' => $publicMessage,
        ];

        // Only include detailed error info in debug mode
        if (config('app.debug')) {
            $error['debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        // Log the full error server-side
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
