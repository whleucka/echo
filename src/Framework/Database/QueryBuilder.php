<?php

namespace Echo\Framework\Database;

use PDOStatement;

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

    public static function select(array $columns = []): QueryBuilder
    {
        $class = get_called_class();
        $qb = new $class();
        if (empty($columns)) {
            $columns = ["*"];
        }
        $qb->mode = "select";
        $qb->select = $columns;
        return $qb;
    }

    public static function insert(array $data = []): QueryBuilder
    {
        $class = get_called_class();
        $qb = new $class();
        $qb->mode = "insert";
        $qb->insert = $data;
        return $qb;
    }

    public static function update(array $data = []): QueryBuilder
    {
        $class = get_called_class();
        $qb = new $class();
        $qb->mode = "update";
        $qb->update = $data;
        return $qb;
    }

    public static function delete(): QueryBuilder
    {
        $class = get_called_class();
        $qb = new $class();
        $qb->mode = "delete";
        return $qb;
    }

    public function getMode()
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
        };
    }

    public function getQueryParams(): array
    {
        return $this->params;
    }

    public function dump()
    {
        return [
            "query" => $this->getQuery(),
            "params" => $this->getQueryParams(),
        ];
    }

    public function from(string $table): QueryBuilder
    {
        $this->table = $table;
        return $this;
    }

    public function into(string $table): QueryBuilder
    {
        $this->table = $table;
        return $this;
    }

    public function table(string $table): QueryBuilder
    {
        $this->table = $table;
        return $this;
    }

    public function where(array $clauses, ...$replacements): QueryBuilder
    {
        $this->where = $clauses;
        $this->addQueryParams($replacements);
        return $this;
    }

    public function orWhere(array $clauses, ...$replacements): QueryBuilder
    {
        $this->orWhere = $clauses;
        $this->addQueryParams($replacements);
        return $this;
    }

    public function groupBy(array $clauses): QueryBuilder
    {
        $this->groupBy = $clauses;
        return $this;
    }

    public function having(array $clauses, ...$replacements): QueryBuilder
    {
        $this->having = $clauses;
        $this->addQueryParams($replacements);
        return $this;
    }

    public function orderBy(array $clauses): QueryBuilder
    {
        $this->orderBy = $clauses;
        return $this;
    }

    public function limit(int $limit): QueryBuilder
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): QueryBuilder
    {
        $this->offset = $offset;
        return $this;
    }

    public function params(array $params): QueryBuilder
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

    private function buildSelect(): string
    {
        if ($this->offset && $this->limit) {
            $limit = " LIMIT $this->offset, $this->limit";
        } elseif ($this->limit > 0) {
            $limit = " LIMIT $this->limit";
        } else {
            $limit = "";
        }
        $sql = sprintf(
            "SELECT %s FROM %s%s%s%s%s%s%s",
            implode(", ", $this->select),
            $this->table,
            $this->where ? " WHERE " . implode(" AND ", $this->where) : "",
            $this->orWhere ? " OR " . implode(" OR ", $this->orWhere) : "",
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
            implode(",", array_fill(0, count($this->insert), "?"))
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
            $this->where ? " WHERE " . implode(" AND ", $this->where) : ""
        );
        return trim($sql);
    }

    private function buildDelete(): string
    {
        $sql = sprintf(
            "DELETE FROM %s%s",
            $this->table,
            $this->where ? " WHERE " . implode(" AND ", $this->where) : ""
        );
        return trim($sql);
    }
}
