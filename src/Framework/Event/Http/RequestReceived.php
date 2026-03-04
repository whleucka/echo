<?php

namespace Echo\Framework\Event\Http;

use Echo\Framework\Event\Event;
use Echo\Framework\Http\RequestInterface;

/**
 * Dispatched when an HTTP request is received, before middleware runs.
 */
class RequestReceived extends Event
{
    public function __construct(
        public readonly RequestInterface $request,
    ) {}
}
