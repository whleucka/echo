<?php

namespace Echo\Framework\Database;

use PDO;

interface ConnectionInterface
{
    public function getLink(): ?PDO;
    public function isConnected(): bool;
}
