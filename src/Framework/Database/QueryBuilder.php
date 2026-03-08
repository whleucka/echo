<?php

namespace Echo\Framework\Database;

use PDOStatement;
use RuntimeException;

class QueryBuilder
{
    private string $mode = "";
    private string $table = "";
    private array $select = [];
    private array $insert = [];
    private array $update = [];
    private array $where = [];
    private array $orWhere = [];
    private array $having = [];
    private array $groupBy = [];
    private array $orderBy = [];
    private int $offset = 0;
    private int $limit = 0;
    private array $params = [];

    public static function select(array $columns = []): static
    {
        $qb = new static();
        if (empty($columns)) {
            $columns = ["*"];
        }
        $qb->mode = "select";
        $qb->select = $columns;
        return $qb;
    }

    public static function insert(array $data = []): static
    {
        $qb = new static();
        $qb->mode = "insert";
        $qb->insert = $data;
        return $qb;
    }

    public static function update(array $data = []): static
    {
        $qb = new static();
        $qb->mode = "update";
        $qb->update = $data;
        return $qb;
    }

    public static function delete(): static
    {
        $qb = new static();
        $qb->mode = "delete";
        return $qb;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getQuery(): string
    {
        return match ($this->mode) {
            "select" => $this->buildSelect(),
            "insert" => $this->buildInsert(),
            "update" => $this->buildUpdate(),
            "delete" => $this->buildDelete(),
            default => throw new RuntimeException(
                "QueryBuilder mode not set. Use select(), insert(), update(), or delete()."
            ),
        };
    }

    public function getQueryParams(): array
    {
        return $this->params;
    }

    public function dump(): array
    {
        return [
            "query" => $this->getQuery(),
            "params" => $this->getQueryParams(),
        ];
    }

    public function from(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function into(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function where(array $clauses, ...$replacements): static
    {
        $this->where = $clauses;
        $this->addQueryParams($replacements);
        return $this;
    }

    public function orWhere(array $clauses, ...$replacements): static
    {
        $this->orWhere = $clauses;
        $this->addQueryParams($replacements);
        return $this;
    }

    public function groupBy(array $clauses): static
    {
        $this->groupBy = $clauses;
        return $this;
    }

    public function having(array $clauses, ...$replacements): static
    {
        $this->having = $clauses;
        $this->addQueryParams($replacements);
        return $this;
    }

    public function orderBy(array $clauses): static
    {
        $this->orderBy = $clauses;
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Set query parameters (overwrites any previously set params)
     *
     * This should be the last call in the chain when providing all
     * parameter values at once, as it replaces existing params.
     */
    public function params(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    public function execute(): bool|PDOStatement
    {
        $query = $this->getQuery();
        $params = $this->getQueryParams();
        return db()->execute($query, $params);
    }

    private function addQueryParams(array $replacements): void
    {
        foreach ($replacements as $replacement) {
            $this->params[] = $replacement;
        }
    }

    private function buildWhereClauses(): string
    {
        $sql = "";
        if ($this->where) {
            $sql .= " WHERE " . implode(" AND ", $this->where);
        }
        if ($this->orWhere) {
            $sql .= ($this->where ? " OR " : " WHERE ") . implode(" OR ", $this->orWhere);
        }
        return $sql;
    }

    private function buildSelect(): string
    {
        $limit = "";
        if ($this->limit > 0 && $this->offset > 0) {
            $limit = " LIMIT $this->limit OFFSET $this->offset";
        } elseif ($this->limit > 0) {
            $limit = " LIMIT $this->limit";
        }

        $sql = sprintf(
            "SELECT %s FROM %s%s%s%s%s%s",
            implode(", ", $this->select),
            $this->table,
            $this->buildWhereClauses(),
            $this->groupBy
                ? " GROUP BY " . implode(", ", $this->groupBy)
                : "",
            $this->having ? " HAVING " . implode(" AND ", $this->having) : "",
            $this->orderBy
                ? " ORDER BY " . implode(", ", $this->orderBy)
                : "",
            $limit
        );
        return trim($sql);
    }

    private function buildInsert(): string
    {
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(", ", array_keys($this->insert)),
            implode(", ", array_fill(0, count($this->insert), "?"))
        );
        return trim($sql);
    }

    private function buildUpdate(): string
    {
        $sql = sprintf(
            "UPDATE %s SET %s%s",
            $this->table,
            implode(
                ", ",
                array_map(
                    fn($column) => "$column = ?",
                    array_keys($this->update)
                )
            ),
            $this->buildWhereClauses()
        );
        return trim($sql);
    }

    private function buildDelete(): string
    {
        $sql = sprintf(
            "DELETE FROM %s%s",
            $this->table,
            $this->buildWhereClauses()
        );
        return trim($sql);
    }
}
