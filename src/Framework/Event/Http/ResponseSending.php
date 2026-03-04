<?php

namespace Echo\Framework\Event\Http;

use Echo\Framework\Event\Event;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;

/**
 * Dispatched after the response is built, before it is sent.
 */
class ResponseSending extends Event
{
    public function __construct(
        public readonly RequestInterface $request,
        public readonly ResponseInterface $response,
    ) {}
}
