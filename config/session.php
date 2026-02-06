<?php

return [
    // Session driver: "file" or "redis"
    "driver" => env("SESSION_DRIVER", "file"),

    // Garbage collection settings
    "gc_maxlifetime" => 10800,
    "gc_probability" => 1,
    "gc_divisor" => 1,
];
