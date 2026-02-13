<?php

return [
    "driver" => env("DB_DRIVER", "mysql"),
    "name" => env("DB_NAME"),
    "username" => env("DB_USERNAME"),
    "password" => env("DB_PASSWORD"),
    "host" => env("DB_HOST", "localhost"),
    "port" => env("DB_PORT", 3306),
    "charset" => env("DB_CHARSET", "utf8mb4"),
    "options" => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => true,
    ]
];
