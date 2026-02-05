<?php

namespace Echo\Interface\Database;

use PDO;

interface Connection
{
    public function getLink(): ?PDO;
    public function isConnected(): bool;
}
