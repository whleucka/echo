<?php

namespace Echo\Framework\Container;

use Echo\Framework\Support\SingletonTrait;
use DI\Container as DIContainer;

class Container
{
    use SingletonTrait;

    public static function getInstance(): DIContainer {
        if (self::$instance === null) {
            $definitions = config("container");
            $container = new DIContainer($definitions);
            self::$instance = $container;
        }
        return self::$instance;
    }
}
