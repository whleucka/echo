<?php

return [
    "host" => env("MAIL_HOST", "localhost"),
    "port" => env("MAIL_PORT", 587),
    "username" => env("MAIL_USERNAME", ""),
    "password" => env("MAIL_PASSWORD", ""),
    "encryption" => env("MAIL_ENCRYPTION", "tls"),
    "from_address" => env("MAIL_FROM_ADDRESS", ""),
    "from_name" => env("MAIL_FROM_NAME", env("APP_NAME", "Echo")),

    // Queue settings
    "max_retries" => 3,
    "retry_delay_minutes" => 5,
    "batch_size" => 20,
];
