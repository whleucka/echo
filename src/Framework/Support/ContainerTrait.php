<?php

namespace Echo\Framework\Support;

trait ContainerTrait
{
    public function __construct(private array $data)
    {
    }

    public function has(string $name): bool
    {
        return $this->__isset($name);
    }

    public function get(string $name): mixed
    {
        return $this->__get($name);
    }

    public function set(string $name, mixed $value): void
    {
        $this->__set($name, $value);
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    public function data(): array
    {
        return $this->data;
    }
}
