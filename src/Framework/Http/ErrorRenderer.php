<?php

namespace Echo\Framework\Http;

use chillerlan\QRCode\QRCode;
use Echo\Framework\Database\Connection;
use Echo\Framework\Http\Exception\HttpException;
use Echo\Framework\Http\Response as HttpResponse;

class ErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private \Twig\Environment $twig,
        private Connection $db,
    ) {}

    public function renderNotFound(RequestInterface $request): ResponseInterface
    {
        return $this->tryRender(fn () => $this->twig->render('error/404.html.twig'), 404);
    }

    public function renderDatabase(\PDOException $e, RequestInterface $request): ResponseInterface
    {
        return $this->tryRender(function () use ($e, $request) {
            $debug = $this->db->debug();
            $extra = "<strong>SQL</strong><pre>" . $debug['sql'] . "</pre>";
            $extra .= "<strong>Params</strong><pre>" . print_r($debug['params'], true) . "</pre>";
            $request_id = $request->getAttribute('request_id');
            return $this->twig->render('error/blue-screen.html.twig', [
                'message' => 'A database error has occurred.',
                'extra' => $extra,
                'debug' => config('app.debug'),
                'request_id' => $request_id,
                'e' => $e,
                'qr' => (new QRCode)->render($request_id),
                'is_logged' => $request->getAttribute('user') !== null,
            ]);
        }, 500);
    }

    public function renderHttp(HttpException $e, RequestInterface $request): ResponseInterface
    {
        return $this->tryRender(function () use ($e, $request) {
            $template = match ($e->statusCode) {
                404 => 'error/404.html.twig',
                403 => 'error/permission-denied.html.twig',
                default => 'error/blue-screen.html.twig',
            };
            $request_id = $request->getAttribute('request_id');
            return $this->twig->render($template, [
                'message' => $e->getMessage(),
                'debug' => config('app.debug'),
                'request_id' => $request_id,
                'e' => $e,
                'is_logged' => $request->getAttribute('user') !== null,
            ]);
        }, $e->statusCode);
    }

    public function renderException(\Throwable $e, RequestInterface $request): ResponseInterface
    {
        return $this->tryRender(function () use ($e, $request) {
            $request_id = $request->getAttribute('request_id');
            return $this->twig->render('error/blue-screen.html.twig', [
                'message' => 'An uncaught error has occurred.',
                'debug' => config('app.debug'),
                'request_id' => $request_id,
                'e' => $e,
                'qr' => (new QRCode)->render($request_id),
                'is_logged' => $request->getAttribute('user') !== null,
            ]);
        }, 500);
    }

    /**
     * Attempt to render via Twig. If Twig itself fails, fall back to a minimal
     * plain-text response so the client always gets something meaningful.
     */
    private function tryRender(callable $render, int $status): ResponseInterface
    {
        try {
            $content = $render();
            return new HttpResponse($content, $status);
        } catch (\Throwable) {
            $content = sprintf(
                "HTTP %d — an error occurred and the error page could not be rendered.",
                $status
            );
            return new HttpResponse($content, $status);
        }
    }
}
