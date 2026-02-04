<?php

namespace Echo\Framework\Database;

use Echo\Framework\Session\Flash;
use Echo\Interface\Database\Connection as DatabaseConnection;
use Echo\Interface\Database\Driver;
use Echo\Traits\Creational\Singleton;
use PDO;
use PDOException;
use PDOStatement;

final class Connection implements DatabaseConnection
{
  use Singleton;

  private bool $connected = false;
  private ?PDO $link = null;
  private Driver $driver;
  private PDOStatement $stmt;
  private array $debug = [];

  public function __construct(Driver $driver)
  {
    $this->driver = $driver;
    $this->connect();
  }

  public static function getInstance(Driver $driver): Connection
  {
    if (self::$instance === null) {
      self::$instance = new self($driver);
    }
    return self::$instance;
  }

  public static function newInstance(Driver $driver): Connection
  {
    self::$instance = new self($driver);
    return self::$instance;
  }

  public function isConnected(): bool
  {
    return $this->connected;
  }

  public function tryConnection(): bool
  {
    $this->connect();
    return $this->connected;
  }

  private function connect(): void
  {
    if ($this->link === null) {
      try {
        $this->connected = true;
        $this->link = new PDO(
          $this->driver->getDsn(),
          $this->driver->getUsername(),
          $this->driver->getPassword(),
          $this->driver->getOptions()
        );
      } catch (PDOException $e) {
        error_log("Please refer to setup guide: https://github.com/whleucka/echo");

        $debug = config("app.debug");
        if ($debug) {
          Flash::add("danger", "Database connection failed.");
        }

        $this->connected = false;

        if (preg_match('/unknown database/i', $e->getMessage())) {
          error_log('Unknown database. ' . $e->getMessage());
        } else if (preg_match('/Name or service not known/', $e->getMessage())) {
          error_log('Unknown database host. ' . $e->getMessage());
        } else {
          error_log('Unknown database error. ' . $e->getMessage());
        }
      }
    }
  }

  public function execute(string $sql, array $params = []): mixed
  {
    if (!$this->connected) return null;

    $startTime = microtime(true);

    $this->debug = [
      'sql' => $sql,
      'params' => $params,
    ];
    $stmt = $this->link->prepare($sql);
    $stmt->execute($params);
    $this->stmt = $stmt;

    // Add profiling hook
    if (config('app.debug') && class_exists('Echo\Framework\Debug\Profiler')) {
      \Echo\Framework\Debug\Profiler::getInstance()->queries()?->log($sql, $params, $startTime);
    }

    return $stmt;
  }

  public function fetch(string $sql, array $params = []): array
  {
    return $this->execute($sql, $params)?->fetch() ?: [];
  }

  public function fetchAll(string $sql, array $params = []): array
  {
    return $this->execute($sql, $params)->fetchAll();
  }

  public function lastInsertId(): string
  {
    return $this->link->lastInsertId();
  }

  public function errorInfo(): array
  {
    return $this->link->errorInfo();
  }

  public function errorCode(): ?string
  {
    return $this->link->errorCode();
  }

  public function debug(): array
  {
    return $this->debug;
  }

  public function beginTransaction(): bool
  {
    return $this->link->beginTransaction();
  }

  public function commit(): bool
  {
    return $this->link->commit();
  }

  public function rollback(): bool
  {
    return $this->link->rollBack();
  }

  public function getLink(): PDO
  {
    return $this->link;
  }
}
