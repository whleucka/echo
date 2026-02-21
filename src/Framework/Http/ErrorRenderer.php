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
        $content = $this->twig->render('error/404.html.twig');
        return new HttpResponse($content, 404);
    }

    public function renderDatabase(\PDOException $e, RequestInterface $request): ResponseInterface
    {
        $debug = $this->db->debug();
        $extra = "<strong>SQL</strong><pre>" . $debug['sql'] . "</pre>";
        $extra .= "<strong>Params</strong><pre>" . print_r($debug['params'], true) . "</pre>";
        $request_id = $request->getAttribute('request_id');
        $content = $this->twig->render('error/blue-screen.html.twig', [
            'message' => 'A database error has occurred.',
            'extra' => $extra,
            'debug' => config('app.debug'),
            'request_id' => $request_id,
            'e' => $e,
            'qr' => (new QRCode)->render($request_id),
            'is_logged' => $request->getAttribute('user') !== null,
        ]);
        return new HttpResponse($content, 500);
    }

    public function renderHttp(HttpException $e, RequestInterface $request): ResponseInterface
    {
        $template = match ($e->statusCode) {
            404 => 'error/404.html.twig',
            403 => 'error/permission-denied.html.twig',
            default => 'error/blue-screen.html.twig',
        };
        $request_id = $request->getAttribute('request_id');
        $content = $this->twig->render($template, [
            'message' => $e->getMessage(),
            'debug' => config('app.debug'),
            'request_id' => $request_id,
            'e' => $e,
            'is_logged' => $request->getAttribute('user') !== null,
        ]);
        return new HttpResponse($content, $e->statusCode);
    }

    public function renderException(\Throwable $e, RequestInterface $request): ResponseInterface
    {
        $request_id = $request->getAttribute('request_id');
        $content = $this->twig->render('error/blue-screen.html.twig', [
            'message' => 'An uncaught error has occurred.',
            'debug' => config('app.debug'),
            'request_id' => $request_id,
            'e' => $e,
            'qr' => (new QRCode)->render($request_id),
            'is_logged' => $request->getAttribute('user') !== null,
        ]);
        return new HttpResponse($content, 500);
    }
}
