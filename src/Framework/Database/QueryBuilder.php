<?php

namespace Echo\Framework\Database;

use PDO;
use PDOStatement;
use RuntimeException;

class QueryBuilder
{
    private string $mode = "";
    private string $table = "";
    private array $select = [];
    private bool $distinct = false;
    private array $joins = [];
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
    private array $onDuplicateUpdate = [];
    private array $unions = [];

    // ─── Static Factory Methods ──────────────────────────────────

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

    /**
     * Create a raw SQL expression
     */
    public static function raw(string $sql, array $bindings = []): Expression
    {
        return new Expression($sql, $bindings);
    }

    /**
     * Create a subquery expression with an alias
     */
    public static function subquery(self $query, string $alias): Expression
    {
        return new Expression(
            "({$query->getQuery()}) AS $alias",
            $query->getQueryParams()
        );
    }

    // ─── Query Inspection ────────────────────────────────────────

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

    // ─── Table ───────────────────────────────────────────────────

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

    // ─── JOINs ───────────────────────────────────────────────────

    public function join(string $table, string $condition, string $type = 'INNER'): static
    {
        $this->joins[] = "$type JOIN $table ON $condition";
        return $this;
    }

    public function leftJoin(string $table, string $condition): static
    {
        return $this->join($table, $condition, 'LEFT');
    }

    public function rightJoin(string $table, string $condition): static
    {
        return $this->join($table, $condition, 'RIGHT');
    }

    public function crossJoin(string $table): static
    {
        $this->joins[] = "CROSS JOIN $table";
        return $this;
    }

    /**
     * Add a raw SQL JOIN clause (e.g. 'LEFT JOIN users ON users.id = t.user_id')
     */
    public function joinRaw(string $sql): static
    {
        $this->joins[] = $sql;
        return $this;
    }

    // ─── WHERE ───────────────────────────────────────────────────

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

    /**
     * Add a WHERE IN clause
     *
     * @param string $column Column name
     * @param array|self $values Array of values or a subquery QueryBuilder
     */
    public function whereIn(string $column, array|self $values): static
    {
        if ($values instanceof self) {
            $this->where[] = "$column IN ({$values->getQuery()})";
            $this->addQueryParams($values->getQueryParams());
        } elseif (empty($values)) {
            $this->where[] = "0 = 1";
        } else {
            $placeholders = implode(", ", array_fill(0, count($values), "?"));
            $this->where[] = "$column IN ($placeholders)";
            $this->addQueryParams($values);
        }
        return $this;
    }

    /**
     * Add a WHERE NOT IN clause
     *
     * @param string $column Column name
     * @param array|self $values Array of values or a subquery QueryBuilder
     */
    public function whereNotIn(string $column, array|self $values): static
    {
        if ($values instanceof self) {
            $this->where[] = "$column NOT IN ({$values->getQuery()})";
            $this->addQueryParams($values->getQueryParams());
        } elseif (empty($values)) {
            $this->where[] = "1 = 1";
        } else {
            $placeholders = implode(", ", array_fill(0, count($values), "?"));
            $this->where[] = "$column NOT IN ($placeholders)";
            $this->addQueryParams($values);
        }
        return $this;
    }

    // ─── GROUP BY / HAVING / ORDER BY ────────────────────────────

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

    // ─── LIMIT / OFFSET ─────────────────────────────────────────

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

    // ─── DISTINCT ────────────────────────────────────────────────

    public function distinct(): static
    {
        $this->distinct = true;
        return $this;
    }

    // ─── UNION ───────────────────────────────────────────────────

    public function union(self $query): static
    {
        $this->unions[] = ['query' => $query, 'all' => false];
        return $this;
    }

    public function unionAll(self $query): static
    {
        $this->unions[] = ['query' => $query, 'all' => true];
        return $this;
    }

    // ─── Upsert ──────────────────────────────────────────────────

    /**
     * Add ON DUPLICATE KEY UPDATE clause to an INSERT
     *
     * @param array $columns Column names to update with their inserted values,
     *                       or an associative array of column => Expression for custom update logic
     */
    public function onDuplicateKeyUpdate(array $columns): static
    {
        $this->onDuplicateUpdate = $columns;
        return $this;
    }

    // ─── Parameters ──────────────────────────────────────────────

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

    // ─── Execution ───────────────────────────────────────────────

    public function execute(): bool|PDOStatement
    {
        $query = $this->getQuery();
        $params = $this->getQueryParams();
        return db()->execute($query, $params);
    }

    // ─── Aggregate Helpers (terminal operations) ─────────────────

    public function count(string $column = '*'): int
    {
        $result = $this->aggregate("COUNT($column)");
        return (int) ($result ?? 0);
    }

    public function sum(string $column): float|int|null
    {
        $result = $this->aggregate("SUM($column)");
        return $result !== null ? $result + 0 : null;
    }

    public function avg(string $column): float|int|null
    {
        $result = $this->aggregate("AVG($column)");
        return $result !== null ? $result + 0 : null;
    }

    public function min(string $column): mixed
    {
        return $this->aggregate("MIN($column)");
    }

    public function max(string $column): mixed
    {
        return $this->aggregate("MAX($column)");
    }

    private function aggregate(string $function): mixed
    {
        $clone = clone $this;
        $clone->select = ["$function AS __aggregate"];
        $clone->orderBy = [];
        $clone->limit = 0;
        $clone->offset = 0;
        $clone->unions = [];
        $result = $clone->execute();
        if ($result instanceof PDOStatement) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            return $row['__aggregate'] ?? null;
        }
        return null;
    }

    // ─── Internal Helpers ────────────────────────────────────────

    private function addQueryParams(array $replacements): void
    {
        foreach ($replacements as $replacement) {
            $this->params[] = $replacement;
        }
    }

    private function buildJoins(): string
    {
        if (empty($this->joins)) {
            return "";
        }
        return " " . implode(" ", $this->joins);
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

    private function buildLimit(): string
    {
        if ($this->limit > 0 && $this->offset > 0) {
            return " LIMIT $this->limit OFFSET $this->offset";
        } elseif ($this->limit > 0) {
            return " LIMIT $this->limit";
        }
        return "";
    }

    // ─── Query Builders ──────────────────────────────────────────

    private function buildSelect(): string
    {
        $distinct = $this->distinct ? "DISTINCT " : "";

        $selectCols = $this->buildSelectColumns();

        $sql = sprintf(
            "SELECT %s%s FROM %s%s%s%s%s%s%s",
            $distinct,
            $selectCols,
            $this->table,
            $this->buildJoins(),
            $this->buildWhereClauses(),
            $this->groupBy
                ? " GROUP BY " . implode(", ", $this->groupBy)
                : "",
            $this->having ? " HAVING " . implode(" AND ", $this->having) : "",
            empty($this->unions)
                ? ($this->orderBy ? " ORDER BY " . implode(", ", $this->orderBy) : "")
                : "",
            empty($this->unions) ? $this->buildLimit() : ""
        );

        $sql = trim($sql);

        // Append UNIONs
        if (!empty($this->unions)) {
            $sql = "($sql)";
            foreach ($this->unions as $union) {
                $keyword = $union['all'] ? "UNION ALL" : "UNION";
                $unionSql = $union['query']->getQuery();
                $sql .= " $keyword ($unionSql)";
                $this->addQueryParams($union['query']->getQueryParams());
            }
            // ORDER BY and LIMIT apply to the full union result
            if ($this->orderBy) {
                $sql .= " ORDER BY " . implode(", ", $this->orderBy);
            }
            $sql .= $this->buildLimit();
            $sql = trim($sql);
        }

        return $sql;
    }

    private function buildSelectColumns(): string
    {
        $parts = [];
        foreach ($this->select as $col) {
            if ($col instanceof Expression) {
                $parts[] = $col->value;
                $this->params = array_merge($col->bindings, $this->params);
            } else {
                $parts[] = $col;
            }
        }
        return implode(", ", $parts);
    }

    private function buildInsert(): string
    {
        $columns = [];
        $placeholders = [];

        foreach ($this->insert as $key => $value) {
            $columns[] = $key;
            if ($value instanceof Expression) {
                $placeholders[] = $value->value;
                $this->addQueryParams($value->bindings);
            } else {
                $placeholders[] = "?";
            }
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(", ", $columns),
            implode(", ", $placeholders)
        );

        // ON DUPLICATE KEY UPDATE
        if (!empty($this->onDuplicateUpdate)) {
            $sql .= " ON DUPLICATE KEY UPDATE " . $this->buildOnDuplicateUpdate();
        }

        return trim($sql);
    }

    private function buildOnDuplicateUpdate(): string
    {
        $parts = [];
        foreach ($this->onDuplicateUpdate as $key => $value) {
            if (is_int($key)) {
                // Numeric key: column name, use VALUES(column)
                $parts[] = "$value = VALUES($value)";
            } elseif ($value instanceof Expression) {
                // Associative key with Expression: custom update logic
                $parts[] = "$key = $value->value";
                $this->addQueryParams($value->bindings);
            } else {
                // Associative key with value: parameterized
                $parts[] = "$key = ?";
                $this->params[] = $value;
            }
        }
        return implode(", ", $parts);
    }

    private function buildUpdate(): string
    {
        $setParts = [];
        foreach ($this->update as $column => $value) {
            if ($value instanceof Expression) {
                $setParts[] = "$column = $value->value";
                $this->addQueryParams($value->bindings);
            } else {
                $setParts[] = "$column = ?";
            }
        }

        $sql = sprintf(
            "UPDATE %s%s SET %s%s",
            $this->table,
            $this->buildJoins(),
            implode(", ", $setParts),
            $this->buildWhereClauses()
        );
        return trim($sql);
    }

    private function buildDelete(): string
    {
        $sql = sprintf(
            "DELETE FROM %s%s%s",
            $this->table,
            $this->buildJoins(),
            $this->buildWhereClauses()
        );
        return trim($sql);
    }
}
