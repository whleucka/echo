<?php

namespace Echo\Interface\Http;

interface Response
{
    public function send(int $code = 200): void;
}
