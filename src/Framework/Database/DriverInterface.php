<?php

namespace Echo\Framework\Database;

interface DriverInterface
{
    public function getDsn(): string;
    public function getUsername(): string;
    public function getPassword(): string;
    public function getOptions(): array;
}
