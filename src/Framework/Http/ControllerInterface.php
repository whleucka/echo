<?php

namespace Echo\Framework\Http;

interface ControllerInterface
{
    public function setRequest(RequestInterface $request): void;
    public function getRequest(): RequestInterface;
}
