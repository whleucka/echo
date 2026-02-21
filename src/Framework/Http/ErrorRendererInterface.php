<?php

namespace Echo\Framework\Http;

use Echo\Framework\Http\Exception\HttpException;

interface ErrorRendererInterface
{
    public function renderNotFound(RequestInterface $request): ResponseInterface;
    public function renderDatabase(\PDOException $e, RequestInterface $request): ResponseInterface;
    public function renderHttp(HttpException $e, RequestInterface $request): ResponseInterface;
    public function renderException(\Throwable $e, RequestInterface $request): ResponseInterface;
}
