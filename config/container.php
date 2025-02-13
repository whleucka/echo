<?php

use Echo\Framework\Database\Drivers\MySQL;
use Echo\Framework\Http\Request;

return [
    Request::class => DI\create()->constructor($_GET, $_POST, $_REQUEST, $_FILES, $_COOKIE, function_exists("getallheaders") ? getallheaders() : []),
    MySQL::class => DI\create()->constructor(
        name: config("db.name"),
        username: config("db.username"),
        password: config("db.password"),
        host: config("db.host"),
        port: (int) config("db.port"),
        charset: config("db.charset"),
        options: config("db.options"),
    ),
    \Twig\Loader\FilesystemLoader::class => DI\create()->constructor(config("paths.templates")),
    \Twig\Environment::class => DI\create()->constructor(DI\Get(\Twig\Loader\FilesystemLoader::class), [
        "cache" => config("paths.template_cache"),
        "auto_reload" => config("app.debug"),
        "debug" => config("app.debug"),
    ])
];
