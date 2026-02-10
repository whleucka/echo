<?php

namespace Echo\Framework\Database\Drivers;

use Echo\Framework\Database\DriverInterface;

class MySQL implements DriverInterface
{
    public function __construct(private string $name, private string $username, private string $password, private string $host = 'localhost', private int $port = 3306, private string $charset = 'utf8mb4', private array $options = [])
    {
    }

    public function getDsn(): string
    {
        return sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s", $this->host, $this->port, $this->name, $this->charset);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
