<?php

namespace Echo\Framework\Database;

use Echo\Framework\Session\Flash;
use Echo\Framework\Support\SingletonTrait;
use PDO;
use PDOException;
use PDOStatement;

final class Connection implements ConnectionInterface
{
  use SingletonTrait;

  private bool $connected = false;
  private ?PDO $link = null;
  private DriverInterface $driver;
  private PDOStatement $stmt;
  private array $debug = [];
  private bool $debugEnabled;
  private bool $profilerAvailable;

  public function __construct(DriverInterface $driver)
  {
    $this->driver = $driver;
    $this->debugEnabled = config('app.debug') ?? false;
    $this->profilerAvailable = class_exists('Echo\Framework\Debug\Profiler');
    $this->connect();
  }

  public static function getInstance(DriverInterface $driver): Connection
  {
    if (self::$instance === null) {
      self::$instance = new self($driver);
    }
    return self::$instance;
  }

  public static function newInstance(DriverInterface $driver): Connection
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

    // Add profiling hook (using cached flags for performance)
    if ($this->debugEnabled && $this->profilerAvailable) {
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
    $stmt = $this->execute($sql, $params);
    if ($stmt === null) {
      throw new \RuntimeException('Database connection not established');
    }
    return $stmt->fetchAll();
  }

  public function lastInsertId(): string
  {
    if ($this->link === null) {
      throw new \RuntimeException('Database connection not established');
    }
    return $this->link->lastInsertId();
  }

  public function errorInfo(): array
  {
    if ($this->link === null) {
      return ['', null, 'Database connection not established'];
    }
    return $this->link->errorInfo();
  }

  public function errorCode(): ?string
  {
    if ($this->link === null) {
      return null;
    }
    return $this->link->errorCode();
  }

  public function debug(): array
  {
    return $this->debug;
  }

  public function beginTransaction(): bool
  {
    if ($this->link === null) {
      throw new \RuntimeException('Database connection not established');
    }
    return $this->link->beginTransaction();
  }

  public function commit(): bool
  {
    if ($this->link === null) {
      throw new \RuntimeException('Database connection not established');
    }
    if ($this->inTransaction()) {
      return $this->link->commit();
    }
    return true;
  }

  public function rollback(): bool
  {
    if ($this->link === null) {
      return false;
    }
    if ($this->inTransaction()) {
      return $this->link->rollBack();
    }
    return false;
  }

  public function inTransaction(): bool
  {
    if ($this->link === null) {
      return false;
    }
    return $this->link->inTransaction();
  }

  public function getLink(): ?PDO
  {
    return $this->link;
  }
}
