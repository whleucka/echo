<?php

return [
    "debug" => env("APP_DEBUG", true),
    "benchmarks" => env("BENCHMARKS_ENABLED", false),
    "url" => env("APP_URL", "http://0.0.0.0"),
    "name" => env("APP_NAME", "Echo"),
    "version" => env("APP_VERSION", "v0.0.1"),
    "timezone" => env("APP_TIMEZONE", "UTC"),
    "key" => env("APP_KEY", ""),
];
