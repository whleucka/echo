<?php

namespace Echo\Framework\Database;

use Echo\Framework\Event\EventDispatcherInterface;
use Echo\Framework\Event\Model\ModelCreating;
use Echo\Framework\Event\Model\ModelCreated;
use Echo\Framework\Event\Model\ModelUpdating;
use Echo\Framework\Event\Model\ModelUpdated;
use Echo\Framework\Event\Model\ModelDeleting;
use Echo\Framework\Event\Model\ModelDeleted;
use InvalidArgumentException;
use JsonSerializable;
use PDO;
use RuntimeException;

abstract class Model implements ModelInterface, JsonSerializable
{
    protected string $tableName;
    protected string $primaryKey = "id";
    protected bool $autoIncrement = true;
    protected array $columns = ["*"];
    protected QueryBuilder $qb;
    private array $where = [];
    private array $orWhere = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private array $having = [];
    private array $params = [];
    protected array $attributes = [];
    private array $originalAttributes = [];
    private array $relations = [];
    private array $eagerLoad = [];
    private array $validOperators = [
        "=",
        "!=",
        ">",
        ">=",
        "<",
        "<=",
        "is",
        "not",
        "like",
    ];

    public function __construct(protected ?string $id = null)
    {
        if (!isset($this->tableName)) {
            throw new RuntimeException(static::class . " must define a tableName property");
        }

        // Initialize the query builder
        $this->qb = new QueryBuilder();

        if (!is_null($id)) {
            $this->loadAttributes($id);
        }
    }

    private static function validateIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z_][\w.]*$/', $identifier)) {
            throw new InvalidArgumentException(
                "Invalid SQL identifier: '$identifier'"
            );
        }
        return $identifier;
    }

    private static function validateDirection(string $direction): string
    {
        $upper = strtoupper($direction);
        if (!in_array($upper, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException(
                "Invalid ORDER BY direction: '$direction'. Must be ASC or DESC."
            );
        }
        return $upper;
    }

    private function loadAttributes(string $id): void
    {
        $key = $this->primaryKey;
        $result = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where(["$key = ?"], $id)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $this->attributes = $result;
            $this->originalAttributes = $result;
        }
    }

    public static function create(array $data): static|bool
    {
        $class = static::class;
        $model = new $class();

        // Dispatch ModelCreating event (allows cancellation)
        $creating = static::fireEvent(new ModelCreating($class, $data));
        if ($creating->isPropagationStopped()) {
            return false;
        }

        $result = $model->qb
            ->insert($data)
            ->into($model->tableName)
            ->execute();
        if ($result && $model->autoIncrement) {
            $id = db()->lastInsertId();
            $created = self::find($id);

            // Dispatch ModelCreated event
            if ($created instanceof static) {
                static::fireEvent(new ModelCreated($created, $created->getAttributes()));
            }

            return $created;
        } elseif ($result && !$model->autoIncrement) {
            return true;
        }
        return false;
    }

    /**
     * Build a chain matching all $find columns with AND. Returns null when
     * $find is empty so callers can decide what to do.
     */
    private static function buildLookupChain(array $find): ?static
    {
        if (empty($find)) {
            return null;
        }
        $chain = null;
        foreach ($find as $column => $value) {
            self::validateIdentifier((string) $column);
            $chain = $chain === null
                ? static::where($column, $value)
                : $chain->andWhere($column, $value);
        }
        return $chain;
    }

    /**
     * Find a row matching $find or create one with $find + $attributes. Use
     * for idempotent seeders, "ensure this exists" job logic, etc.
     *
     * @throws InvalidArgumentException if $find is empty
     */
    public static function firstOrCreate(array $find, array $attributes = []): static|bool
    {
        if (empty($find)) {
            throw new InvalidArgumentException(
                "firstOrCreate(): \$find must contain at least one column => value pair"
            );
        }
        $existing = self::buildLookupChain($find)?->first();
        if ($existing !== null) {
            return $existing;
        }
        return static::create([...$find, ...$attributes]);
    }

    /**
     * Find a row matching $find and apply $attributes via update(), or create
     * a new row with $find + $attributes. Returns the live model.
     *
     * @throws InvalidArgumentException if $find is empty
     */
    public static function updateOrCreate(array $find, array $attributes = []): static|bool
    {
        if (empty($find)) {
            throw new InvalidArgumentException(
                "updateOrCreate(): \$find must contain at least one column => value pair"
            );
        }
        $existing = self::buildLookupChain($find)?->first();
        if ($existing !== null) {
            if (!empty($attributes)) {
                $existing->update($attributes);
            }
            return $existing;
        }
        return static::create([...$find, ...$attributes]);
    }

    public static function find(string $id): ?static
    {
        $class = static::class;
        try {
            $model = new $class($id);
            // $model->id is the constructor argument, set whether or not a
            // row was found. Confirm the row actually loaded by checking that
            // attributes contain the primary key.
            $loaded = $model->attributes[$model->primaryKey] ?? null;
            return $loaded !== null ? $model : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Find a row by primary key or throw ModelNotFoundException. The exception
     * carries the model class and id so controllers can render a 404 without
     * re-running the query.
     */
    public static function findOrFail(string $id): static
    {
        $model = static::find($id);
        if ($model === null) {
            throw new ModelNotFoundException(static::class, $id);
        }
        return $model;
    }

    /**
     * Start an unfiltered query chain. Equivalent to (new static()) but reads
     * better at call sites — preferred over anchor hacks like
     * Model::where("id", ">", 0) when you just want to chain select/groupBy/etc.
     */
    public static function query(): static
    {
        return new static();
    }

    /**
     * Conditionally apply $callback to the chain when $value is truthy.
     * The callback receives ($this, $value) and may return either the
     * chained instance or void/null (which falls back to $this).
     *
     * Replaces the verbose `if ($filter) { $q = $q->where(...); }` pattern in
     * controllers that build queries from request input.
     *
     *   User::query()
     *       ->when($search, fn($q, $v) => $q->where('name', 'like', "%$v%"))
     *       ->when($role,   fn($q, $v) => $q->where('role', $v))
     *       ->paginate(20);
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        if ($value) {
            $result = $callback($this, $value);
            return $result instanceof static ? $result : $this;
        }
        if ($default !== null) {
            $result = $default($this, $value);
            return $result instanceof static ? $result : $this;
        }
        return $this;
    }

    /**
     * Inverse of when(): apply $callback when $value is falsy.
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static
    {
        return $this->when(!$value, $callback, $default);
    }

    public static function where(string $field, string $operator = '=', ?string $value = null): static
    {
        self::validateIdentifier($field);
        $class = static::class;
        $model = new $class();

        // Default operator is =
        if (!in_array(strtolower($operator), $model->validOperators)) {
            $value = $operator;
            $operator = "=";
        }
        // Add the where clause and params
        $model->where[] = "($field $operator ?)";
        $model->params[] = $value;
        return $model;
    }

    public function orWhere(string $field, string $operator = '=', ?string $value = null): static
    {
        self::validateIdentifier($field);
        // Default operator is =
        if (!in_array(strtolower($operator), $this->validOperators)) {
            $value = $operator;
            $operator = "=";
        }
        // Add the where clause and params
        $this->orWhere[] = "($field $operator ?)";
        $this->params[] = $value;
        return $this;
    }

    public function andWhere(string $field, string $operator = '=', ?string $value = null): static
    {
        self::validateIdentifier($field);
        // Default operator is =
        if (!in_array(strtolower($operator), $this->validOperators)) {
            $value = $operator;
            $operator = "=";
        }
        // Add the where clause and params
        $this->where[] = "($field $operator ?)";
        $this->params[] = $value;
        return $this;
    }

    /**
     * Add a raw WHERE clause
     *
     * @param string $sql Raw SQL condition
     * @param array $params Parameters for the condition
     * @return static
     */
    public function whereRaw(string $sql, array $params = []): static
    {
        $this->where[] = "($sql)";
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Add a WHERE BETWEEN clause
     *
     * @param string $field Column name
     * @param mixed $start Start value
     * @param mixed $end End value
     * @return static
     */
    public function whereBetween(string $field, mixed $start, mixed $end): static
    {
        self::validateIdentifier($field);
        $this->where[] = "($field BETWEEN ? AND ?)";
        $this->params[] = $start;
        $this->params[] = $end;
        return $this;
    }

    /**
     * Add a WHERE IS NULL clause
     *
     * @param string $field Column name
     * @return static
     */
    public function whereNull(string $field): static
    {
        self::validateIdentifier($field);
        $this->where[] = "($field IS NULL)";
        return $this;
    }

    /**
     * Add a WHERE IS NOT NULL clause
     *
     * @param string $field Column name
     * @return static
     */
    public function whereNotNull(string $field): static
    {
        self::validateIdentifier($field);
        $this->where[] = "($field IS NOT NULL)";
        return $this;
    }

    /**
     * Add a WHERE NOT BETWEEN clause — complement of whereBetween().
     */
    public function whereNotBetween(string $field, mixed $start, mixed $end): static
    {
        self::validateIdentifier($field);
        $this->where[] = "($field NOT BETWEEN ? AND ?)";
        $this->params[] = $start;
        $this->params[] = $end;
        return $this;
    }

    /**
     * Filter by DATE($column) using MySQL's DATE() function. Operator accepts
     * the same set as where(); a non-operator second arg is treated as the
     * value (defaults to =).
     *
     *   $q->whereDate('created_at', '2026-05-22');
     *   $q->whereDate('created_at', '>=', '2026-01-01');
     */
    public function whereDate(string $column, string $operator, ?string $date = null): static
    {
        self::validateIdentifier($column);
        if (!in_array(strtolower($operator), $this->validOperators)) {
            $date = $operator;
            $operator = "=";
        }
        $this->where[] = "(DATE($column) $operator ?)";
        $this->params[] = $date;
        return $this;
    }

    /**
     * Filter by YEAR($column).
     */
    public function whereYear(string $column, int|string $year): static
    {
        self::validateIdentifier($column);
        $this->where[] = "(YEAR($column) = ?)";
        $this->params[] = $year;
        return $this;
    }

    /**
     * Filter by MONTH($column). Pass 1-12.
     */
    public function whereMonth(string $column, int|string $month): static
    {
        self::validateIdentifier($column);
        $this->where[] = "(MONTH($column) = ?)";
        $this->params[] = $month;
        return $this;
    }

    /**
     * Filter by DAY($column). Pass 1-31.
     */
    public function whereDay(string $column, int|string $day): static
    {
        self::validateIdentifier($column);
        $this->where[] = "(DAY($column) = ?)";
        $this->params[] = $day;
        return $this;
    }

    /**
     * Filter by TIME($column). Operator follows the same defaulting rule as
     * whereDate(): a non-operator arg becomes the value.
     */
    public function whereTime(string $column, string $operator, ?string $time = null): static
    {
        self::validateIdentifier($column);
        if (!in_array(strtolower($operator), $this->validOperators)) {
            $time = $operator;
            $operator = "=";
        }
        $this->where[] = "(TIME($column) $operator ?)";
        $this->params[] = $time;
        return $this;
    }

    /**
     * Start a query with a WHERE IN clause.
     *
     * Mirrors where()'s static-factory pattern. An empty $values produces a
     * `0 = 1` condition so the query returns no rows (safer than returning all).
     */
    public static function whereIn(string $field, array $values): static
    {
        self::validateIdentifier($field);
        $class = static::class;
        $model = new $class();
        $model->applyWhereIn($field, $values, negate: false);
        return $model;
    }

    /**
     * Add a WHERE IN clause to an existing query.
     */
    public function andWhereIn(string $field, array $values): static
    {
        self::validateIdentifier($field);
        $this->applyWhereIn($field, $values, negate: false);
        return $this;
    }

    /**
     * Add a WHERE NOT IN clause to an existing query.
     */
    public function andWhereNotIn(string $field, array $values): static
    {
        self::validateIdentifier($field);
        $this->applyWhereIn($field, $values, negate: true);
        return $this;
    }

    private function applyWhereIn(string $field, array $values, bool $negate): void
    {
        if (empty($values)) {
            $this->where[] = $negate ? "(1 = 1)" : "(0 = 1)";
            return;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $op = $negate ? 'NOT IN' : 'IN';
        $this->where[] = "($field $op ($placeholders))";
        foreach ($values as $value) {
            $this->params[] = $value;
        }
    }

    public function orderBy(string $column, string $direction = "ASC"): static
    {
        self::validateIdentifier($column);
        $direction = self::validateDirection($direction);
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    /**
     * Add a GROUP BY clause
     *
     * @param string ...$columns Columns to group by
     * @return static
     */
    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $col) {
            self::validateIdentifier($col);
        }
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    /**
     * Append a raw ORDER BY expression. Bypasses identifier validation, so use
     * only for trusted SQL (function calls like COALESCE(), FIELD(), RAND()).
     *
     * Bindings, if any, are appended positionally to query params. Chain order
     * matters: call after where() and after having(), since SQL is assembled
     * WHERE -> HAVING -> ORDER BY.
     */
    public function orderByRaw(string $expr, array $bindings = []): static
    {
        $this->orderBy[] = $expr;
        foreach ($bindings as $binding) {
            $this->params[] = $binding;
        }
        return $this;
    }

    /**
     * Order by a timestamp column descending. Defaults to created_at — sugar
     * for the most common "newest first" listing pattern.
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Order by a timestamp column ascending. Defaults to created_at.
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Append a raw GROUP BY expression. Bypasses identifier validation, so use
     * only for trusted SQL (DATE(), YEAR(), etc.). Chain after where().
     */
    public function groupByRaw(string $expr, array $bindings = []): static
    {
        $this->groupBy[] = $expr;
        foreach ($bindings as $binding) {
            $this->params[] = $binding;
        }
        return $this;
    }

    /**
     * Add HAVING clauses for use with groupBy(). Each clause is wrapped in
     * parens and joined with AND, matching the where() convention.
     *
     * Chain after where() and before orderBy() to keep bindings positionally
     * aligned with WHERE -> HAVING -> ORDER BY.
     */
    public function having(array $clauses, mixed ...$replacements): static
    {
        foreach ($clauses as $clause) {
            $this->having[] = "($clause)";
        }
        foreach ($replacements as $replacement) {
            $this->params[] = $replacement;
        }
        return $this;
    }

    /**
     * Add a raw HAVING clause. Mirrors whereRaw().
     */
    public function havingRaw(string $sql, array $bindings = []): static
    {
        $this->having[] = "($sql)";
        foreach ($bindings as $binding) {
            $this->params[] = $binding;
        }
        return $this;
    }

    /**
     * Set custom select columns (for aggregates, expressions, etc.)
     *
     * @param array $columns Columns or expressions to select
     * @return static
     */
    public function select(array $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Get raw results as arrays (useful for GROUP BY / aggregate queries)
     *
     * @param int $limit Maximum number of results (0 = no limit)
     * @return array
     */
    public function getRaw(int $limit = 0): array
    {
        $results = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->groupBy($this->groupBy)
            ->having($this->having)
            ->orderBy($this->orderBy)
            ->limit($limit)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);

        return $results ?: [];
    }

    /**
     * Return a flat array of a single column's values from the current chain.
     *
     * Mirrors Laravel's pluck(): runs a `SELECT $column` honoring where /
     * groupBy / having / orderBy, then collapses to a positionally-indexed
     * array. Replaces the common `array_column(Model::...->getRaw(), $col)`
     * idiom at call sites.
     *
     * Dotted aliases (e.g. "u.name") are resolved using the trailing segment
     * as the result key.
     */
    public function pluck(string $column): array
    {
        self::validateIdentifier($column);
        $results = $this->qb
            ->select([$column])
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->groupBy($this->groupBy)
            ->having($this->having)
            ->orderBy($this->orderBy)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);

        if (!$results) {
            return [];
        }
        $key = str_contains($column, '.') ? substr(strrchr($column, '.'), 1) : $column;
        return array_column($results, $key);
    }

    /**
     * Run the chain and return hydrated models indexed by $column. Replaces
     * the common `foreach ($models as $m) { $map[$m->id] = $m; }` idiom.
     *
     * Rows where $column is null are skipped. If duplicate keys appear, the
     * later row wins (matching array-assignment semantics).
     */
    public function keyBy(string $column): array
    {
        self::validateIdentifier($column);
        $models = $this->get();
        if (empty($models)) {
            return [];
        }
        $bare = str_contains($column, '.') ? substr(strrchr($column, '.'), 1) : $column;
        $keyed = [];
        foreach ($models as $model) {
            $value = $model->$bare ?? null;
            if ($value !== null) {
                $keyed[$value] = $model;
            }
        }
        return $keyed;
    }

    /**
     * Return a single column's value from the first matching row, or null if
     * no row matches. Sugar for `first()?->$column` that avoids hydrating the
     * full row when only one column is needed.
     */
    public function value(string $column): mixed
    {
        self::validateIdentifier($column);
        $result = $this->qb
            ->select([$column])
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->orderBy($this->orderBy)
            ->limit(1)
            ->params($this->params)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }
        $key = str_contains($column, '.') ? substr(strrchr($column, '.'), 1) : $column;
        return $result[$key] ?? null;
    }

    /**
     * Add eager loading to a query chain.
     *
     * Instance-only by design: a static factory was removed to eliminate the
     * Model::with('x')->where(...) foot-gun, where static where() created a
     * fresh model and silently dropped the eagerLoad. Start chains with
     * query(), where(), whereIn(), or find() instead, then chain with().
     *
     *   Model::query()->with('x')->where(...)->get()   // OK
     *   Model::where(...)->with('x')->get()            // OK
     */
    public function with(string ...$relations): static
    {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
    }

    /**
     * Alias of with(). Retained for back-compat with callers that adopted
     * load() before the static with() factory was demoted.
     */
    public function load(string ...$relations): static
    {
        return $this->with(...$relations);
    }

    public function refresh(): static
    {
        $this->loadAttributes($this->id);
        return $this;
    }

    public function get(int $limit = 0): array
    {
        $results = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->groupBy($this->groupBy)
            ->having($this->having)
            ->orderBy($this->orderBy)
            ->limit($limit)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_OBJ);

        if (!$results) {
            return [];
        }

        // Hydrate results
        $models = array_map(fn($row) => static::hydrate($row), $results);

        // Perform eager loading if specified
        if (!empty($this->eagerLoad)) {
            $this->loadRelations($models, $this->eagerLoad);
        }

        return $models;
    }

    /**
     * Batch-load eager-loaded relations for a collection of models.
     *
     * One query per relation regardless of result set size: the relation
     * method is invoked under captureRelation() to extract its descriptor
     * without firing a query, then a single
     * `WHERE related_column IN (parent_column_values...)` query is issued
     * and results are grouped and stitched onto each parent. Each parent's
     * cache (relations[$name]) is populated, so subsequent lazy method calls
     * like $model->name() reuse the eager-loaded data.
     *
     * Dotted paths (e.g. `track.meta`) trigger nested loading: after the
     * first-hop children are stitched, this method recurses on the children
     * with the remainder of the path. Shared first segments are deduplicated
     * — `with('track.meta', 'track.artist', 'client')` runs one batched
     * query for tracks, then recurses with `[meta, artist]` on those tracks,
     * plus one batched query for clients.
     */
    private function loadRelations(array &$models, array $eagerLoad): void
    {
        if (empty($models) || empty($eagerLoad)) {
            return;
        }

        // Group dotted paths by their first segment so a shared first hop only
        // runs one batched query regardless of how many nested paths use it.
        $grouped = [];
        foreach ($eagerLoad as $path) {
            [$first, $rest] = array_pad(explode('.', $path, 2), 2, null);
            $grouped[$first] ??= [];
            if ($rest !== null) {
                $grouped[$first][] = $rest;
            }
        }

        foreach ($grouped as $first => $nested) {
            if (!method_exists($this, $first)) {
                continue;
            }

            $prototype = new static();
            $descriptor = self::captureRelation(fn() => $prototype->$first());
            if ($descriptor === null) {
                continue;
            }

            $isMany = $descriptor->type === Relation::HAS_MANY;
            $empty = $isMany ? [] : null;

            // Collect parent key values
            $keys = [];
            foreach ($models as $model) {
                $value = $model->{$descriptor->parentColumn} ?? null;
                if ($value !== null) {
                    $keys[] = $value;
                }
            }
            $keys = array_values(array_unique($keys));

            // Group children by their join column; also build a flat list for
            // nested recursion.
            $childrenByKey = [];
            $allChildren = [];
            if (!empty($keys)) {
                $relatedClass = $descriptor->related;
                $childModels = $relatedClass::whereIn($descriptor->relatedColumn, $keys)->get();
                foreach ($childModels as $child) {
                    $childKey = $child->{$descriptor->relatedColumn} ?? null;
                    if ($childKey === null) {
                        continue;
                    }
                    if ($isMany) {
                        $childrenByKey[$childKey][] = $child;
                    } else {
                        $childrenByKey[$childKey] ??= $child;
                    }
                    $allChildren[] = $child;
                }
            }

            // Stitch results onto each parent's relations cache
            foreach ($models as $model) {
                $value = $model->{$descriptor->parentColumn} ?? null;
                $model->relations[$first] = ($value !== null && isset($childrenByKey[$value]))
                    ? $childrenByKey[$value]
                    : $empty;
            }

            // Recurse for nested paths on the loaded children
            if (!empty($nested) && !empty($allChildren)) {
                $allChildren[0]->loadRelations($allChildren, $nested);
            }
        }
    }

    /**
     * Paginate the query and return a page of hydrated models with metadata.
     *
     * Runs a COUNT (respecting WHERE/orWhere) followed by a SELECT with
     * LIMIT/OFFSET. Eager-loaded relations from with() are batched on the
     * resulting page exactly as in get().
     *
     * Does NOT support groupBy() or having() — for grouped queries, compute
     * the count manually and use getRaw() with limit().
     *
     * Returned shape: ['data', 'total', 'page', 'perPage', 'lastPage'].
     *
     * @throws InvalidArgumentException if $perPage is < 1
     * @throws RuntimeException if groupBy() or having() is set
     */
    public function paginate(int $perPage, int $page = 1): array
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException(
                "paginate(): perPage must be >= 1, got $perPage"
            );
        }
        if (!empty($this->groupBy) || !empty($this->having)) {
            throw new RuntimeException(
                "paginate() does not support groupBy() or having(). "
                . "Compute the count manually for grouped queries."
            );
        }
        if ($page < 1) {
            $page = 1;
        }

        $total = $this->count();

        if ($total === 0) {
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'perPage' => $perPage,
                'lastPage' => 1,
            ];
        }

        $offset = ($page - 1) * $perPage;

        $results = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->orderBy($this->orderBy)
            ->limit($perPage)
            ->offset($offset)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_OBJ);

        $models = $results
            ? array_map(fn($row) => static::hydrate($row), $results)
            : [];

        if (!empty($this->eagerLoad) && !empty($models)) {
            $this->loadRelations($models, $this->eagerLoad);
        }

        return [
            'data' => $models,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Process the query result in batches of $size, passing each batch as an
     * array of hydrated models to $callback. The callback receives
     * ($models, $page) and may return false to stop iteration early.
     *
     * Requires an ORDER BY clause for deterministic pagination — without one,
     * the database is free to return rows in any order, which makes
     * LIMIT/OFFSET unsafe across pages. Add ->orderBy('id') or similar.
     *
     * Like paginate(), does NOT support groupBy() or having() — the LIMIT
     * applies to grouped rows, not source rows, which breaks chunking.
     *
     * Returns true if iteration completed, false if the callback aborted.
     *
     * @throws InvalidArgumentException if $size < 1
     * @throws RuntimeException if no orderBy/groupBy/having constraints violated
     */
    public function chunk(int $size, callable $callback): bool
    {
        if ($size < 1) {
            throw new InvalidArgumentException(
                "chunk(): \$size must be >= 1, got $size"
            );
        }
        if (empty($this->orderBy)) {
            throw new RuntimeException(
                "chunk() requires an ORDER BY clause for deterministic "
                . "pagination. Add ->orderBy(...) to the chain (commonly the "
                . "primary key)."
            );
        }
        if (!empty($this->groupBy) || !empty($this->having)) {
            throw new RuntimeException(
                "chunk() does not support groupBy() or having(). The LIMIT "
                . "applies to grouped rows, which breaks chunking."
            );
        }

        $page = 0;
        do {
            $offset = $page * $size;
            $results = $this->qb
                ->select($this->columns)
                ->from($this->tableName)
                ->where($this->where)
                ->orWhere($this->orWhere)
                ->orderBy($this->orderBy)
                ->limit($size)
                ->offset($offset)
                ->params($this->params)
                ->execute()
                ->fetchAll(PDO::FETCH_OBJ);

            if (!$results) {
                break;
            }

            $models = array_map(fn($row) => static::hydrate($row), $results);
            if (!empty($this->eagerLoad)) {
                $this->loadRelations($models, $this->eagerLoad);
            }

            if ($callback($models, $page + 1) === false) {
                return false;
            }

            $page++;
        } while (count($results) === $size);

        return true;
    }

    /**
     * Get an eager loaded relation
     */
    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    public function first(): ?static
    {
        $results = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->orderBy($this->orderBy)
            ->limit(1)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_OBJ);

        if ($results) {
            return static::hydrate($results[0]);
        }
        return null;
    }

    /**
     * Return the first matching row or throw ModelNotFoundException. Use in
     * controller paths where a missing row is a 404, not a recoverable nil.
     */
    public function firstOrFail(): static
    {
        $result = $this->first();
        if ($result === null) {
            throw new ModelNotFoundException(static::class);
        }
        return $result;
    }

    /**
     * Return true if any row matches the current chain. Honors where /
     * orWhere / groupBy / having; cheaper than count() since it short-circuits
     * via LIMIT 1.
     */
    public function exists(): bool
    {
        $result = $this->qb
            ->select(["1"])
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->groupBy($this->groupBy)
            ->having($this->having)
            ->limit(1)
            ->params($this->params)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return $result !== false && $result !== null;
    }

    /**
     * Inverse of exists().
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Count records matching the query
     *
     * @param string $column Column to count (default '*')
     * @return int
     */
    public function count(string $column = '*'): int
    {
        $result = $this->qb
            ->select(["COUNT($column) as aggregate"])
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->params($this->params)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return (int)($result['aggregate'] ?? 0);
    }

    /**
     * Static count with no conditions
     *
     * @param string $column Column to count (default '*')
     * @return int
     */
    public static function countAll(string $column = '*'): int
    {
        $class = static::class;
        $model = new $class();
        return $model->count($column);
    }

    /**
     * Get the maximum value of a column
     *
     * @param string $column Column name
     * @return mixed
     */
    public function max(string $column): mixed
    {
        $result = $this->qb
            ->select(["MAX($column) as aggregate"])
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->params($this->params)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return $result['aggregate'] ?? null;
    }

    /**
     * Static max with no conditions
     *
     * @param string $column Column name
     * @return mixed
     */
    public static function maxAll(string $column): mixed
    {
        $class = static::class;
        $model = new $class();
        return $model->max($column);
    }

    /**
     * Minimum value of $column across rows matching the current chain.
     */
    public function min(string $column): mixed
    {
        $result = $this->qb
            ->select(["MIN($column) as aggregate"])
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->params($this->params)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return $result['aggregate'] ?? null;
    }

    /**
     * Static min with no conditions.
     */
    public static function minAll(string $column): mixed
    {
        $class = static::class;
        $model = new $class();
        return $model->min($column);
    }

    /**
     * Sum of $column across rows matching the current chain. Returns a numeric
     * string (PDO's native MySQL type for SUM); cast at the call site if you
     * need int/float.
     */
    public function sum(string $column): mixed
    {
        $result = $this->qb
            ->select(["SUM($column) as aggregate"])
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->params($this->params)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return $result['aggregate'] ?? null;
    }

    /**
     * Static sum with no conditions.
     */
    public static function sumAll(string $column): mixed
    {
        $class = static::class;
        $model = new $class();
        return $model->sum($column);
    }

    /**
     * Average of $column across rows matching the current chain. Returns a
     * numeric string from MySQL; cast at the call site if needed.
     */
    public function avg(string $column): mixed
    {
        $result = $this->qb
            ->select(["AVG($column) as aggregate"])
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->params($this->params)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return $result['aggregate'] ?? null;
    }

    /**
     * Static avg with no conditions.
     */
    public static function avgAll(string $column): mixed
    {
        $class = static::class;
        $model = new $class();
        return $model->avg($column);
    }

    public function last(): ?static
    {
        // Reverse each ORDER BY direction so LIMIT 1 gets the last row
        $reversedOrder = array_map(function (string $clause): string {
            if (str_ends_with($clause, ' ASC')) {
                return substr($clause, 0, -4) . ' DESC';
            } elseif (str_ends_with($clause, ' DESC')) {
                return substr($clause, 0, -5) . ' ASC';
            }
            return $clause . ' DESC';
        }, $this->orderBy);

        $results = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->orderBy($reversedOrder)
            ->limit(1)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_OBJ);

        if ($results) {
            return static::hydrate($results[0]);
        }
        return null;
    }

    public function sql(int $limit = 0): array
    {
        $qb = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->groupBy($this->groupBy)
            ->having($this->having)
            ->orderBy($this->orderBy)
            ->limit($limit)
            ->params($this->params);
        return ["query" => $qb->getQuery(), "params" => $qb->getQueryParams()];
    }

    public function save(): static
    {
        $oldAttributes = $this->originalAttributes;

        // Dispatch ModelUpdating event (allows cancellation)
        $updating = static::fireEvent(new ModelUpdating($this, $oldAttributes, $this->attributes));
        if ($updating->isPropagationStopped()) {
            return $this;
        }

        $key = $this->primaryKey;
        $result = $this->qb
            ->update($this->attributes)
            ->table($this->tableName)
            ->where(["$key = ?"], $this->id)
            ->execute();
        if ($result) {
            $this->loadAttributes($this->id);

            // Dispatch ModelUpdated event
            static::fireEvent(new ModelUpdated($this, $oldAttributes, $this->getAttributes()));
        }
        return $this;
    }

    public function update(array $data): static
    {
        $oldAttributes = $this->originalAttributes;

        // Dispatch ModelUpdating event (allows cancellation)
        $updating = static::fireEvent(new ModelUpdating($this, $oldAttributes, $data));
        if ($updating->isPropagationStopped()) {
            return $this;
        }

        $key = $this->primaryKey;
        $result = $this->qb
            ->update($data)
            ->table($this->tableName)
            ->where(["$key = ?"], $this->id)
            ->execute();
        if ($result) {
            $this->loadAttributes($this->id);

            // Dispatch ModelUpdated event
            static::fireEvent(new ModelUpdated($this, $oldAttributes, $this->getAttributes()));
        }
        return $this;
    }

    /**
     * Atomically increment $column by $amount on the current row using
     * `SET col = col + ?`. Avoids the read-modify-write race that plain
     * update() would have.
     *
     * Does NOT fire ModelUpdating/Updated events — that would require fetching
     * old/new state, which defeats the atomicity. Call save()/update() if you
     * need events.
     */
    public function increment(string $column, int $amount = 1): bool
    {
        return $this->adjustColumn($column, '+', $amount);
    }

    /**
     * Atomically decrement $column by $amount on the current row. See
     * increment() for caveats — same atomicity guarantee, same event bypass.
     */
    public function decrement(string $column, int $amount = 1): bool
    {
        return $this->adjustColumn($column, '-', $amount);
    }

    private function adjustColumn(string $column, string $op, int $amount): bool
    {
        self::validateIdentifier($column);
        if ($this->id === null) {
            return false;
        }
        $key = $this->primaryKey;
        $sql = "UPDATE {$this->tableName} SET {$column} = {$column} {$op} ? WHERE {$key} = ?";
        $result = db()->execute($sql, [$amount, $this->id]);
        if ($result === false || $result === null) {
            return false;
        }
        $this->loadAttributes($this->id);
        return true;
    }

    /**
     * Set updated_at to the current timestamp on the row, without altering
     * any other columns. Bypasses model events for the same reason
     * increment()/decrement() do.
     */
    public function touch(): bool
    {
        if ($this->id === null) {
            return false;
        }
        $key = $this->primaryKey;
        $sql = "UPDATE {$this->tableName} SET updated_at = ? WHERE {$key} = ?";
        $result = db()->execute($sql, [date('Y-m-d H:i:s'), $this->id]);
        if ($result === false || $result === null) {
            return false;
        }
        $this->loadAttributes($this->id);
        return true;
    }

    public function delete(): bool
    {
        $attributes = $this->getAttributes();

        // Dispatch ModelDeleting event (allows cancellation)
        $deleting = static::fireEvent(new ModelDeleting($this, $attributes));
        if ($deleting->isPropagationStopped()) {
            return false;
        }

        $key = $this->primaryKey;
        $result = $this->qb
            ->delete()
            ->from($this->tableName)
            ->where(["$key = ?"], $this->id)
            ->execute();

        if ($result) {
            // Dispatch ModelDeleted event
            static::fireEvent(new ModelDeleted(static::class, $this->id, $attributes));
        }

        return (bool) $result;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Return the model's attributes with any populated relations folded in.
     *
     * Only relations already loaded (eagerly via with() or cached from a prior
     * lazy method call) are included — unloaded relations are NOT triggered to
     * load here. Child models recurse via toArray(), so nested relations are
     * serialized too.
     */
    public function toArray(): array
    {
        $out = $this->attributes;
        foreach ($this->relations as $name => $value) {
            if ($value instanceof Model) {
                $out[$name] = $value->toArray();
            } elseif (is_array($value)) {
                $out[$name] = array_map(
                    fn($v) => $v instanceof Model ? $v->toArray() : $v,
                    $value
                );
            } else {
                $out[$name] = $value;
            }
        }
        return $out;
    }

    /**
     * JSON-encode the model via toArray(). Pass json_encode flags as needed.
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    /**
     * JsonSerializable contract — lets the model json_encode cleanly without
     * an explicit toArray() call site (json_encode($model) just works).
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * True if any attribute (or the given attribute) has changed since the
     * row was loaded from the database. New attributes that didn't exist in
     * the original payload count as dirty too.
     */
    public function isDirty(?string $column = null): bool
    {
        $dirty = $this->getDirty();
        if ($column === null) {
            return !empty($dirty);
        }
        return array_key_exists($column, $dirty);
    }

    /**
     * Inverse of isDirty().
     */
    public function isClean(?string $column = null): bool
    {
        return !$this->isDirty($column);
    }

    /**
     * Return only the attributes whose current value differs from the
     * originally-loaded value (or whose key did not exist in the original).
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->originalAttributes)
                || $this->originalAttributes[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * Get an attribute's value as it was when the row was last loaded.
     * Returns the full original attribute map when $column is null.
     */
    public function getOriginal(?string $column = null): mixed
    {
        if ($column === null) {
            return $this->originalAttributes;
        }
        return $this->originalAttributes[$column] ?? null;
    }

    /**
     * Return a new instance with re-fetched data, leaving the current
     * instance untouched. Use refresh() when you want to mutate in place.
     * Returns null if the row no longer exists.
     */
    public function fresh(): ?static
    {
        if ($this->id === null) {
            return null;
        }
        return static::find($this->id);
    }

    /**
     * Get the table name for this model
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Get the primary key value
     */
    public function getId(): string|int|null
    {
        return $this->id;
    }

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function __get($name)
    {
        // Column attribute wins over relations (e.g. a `user_id` FK column should
        // not be shadowed by an eager-loaded `user` relation that happens to be
        // accessed via the same name).
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }
        return null;
    }

    public function __isset($name): bool
    {
        return array_key_exists($name, $this->attributes)
            || array_key_exists($name, $this->relations);
    }

    /**
     * Hydrate a model instance from data without additional queries
     */
    protected static function hydrate(object $data): static
    {
        $class = static::class;
        $model = new $class();
        $model->attributes = (array) $data;
        $model->originalAttributes = $model->attributes;
        $model->id = $data->{$model->primaryKey} ?? null;
        return $model;
    }

    /**
     * Stack of capture slots used by the eager loader. When non-empty, the next
     * hasOne / hasMany / belongsTo call stores its Relation descriptor into the
     * top slot instead of executing a query.
     */
    private static array $captureStack = [];

    /**
     * Run $callback in "capture mode" and return the Relation descriptor that
     * its hasOne / hasMany / belongsTo call built.
     *
     * Used by the eager loader to introspect a relation method without
     * executing a database query.
     */
    public static function captureRelation(callable $callback): ?Relation
    {
        self::$captureStack[] = null;
        try {
            $callback();
        } finally {
            $captured = array_pop(self::$captureStack);
        }
        return $captured instanceof Relation ? $captured : null;
    }

    private static function inCaptureMode(): bool
    {
        return !empty(self::$captureStack);
    }

    private static function capture(Relation $relation): void
    {
        $top = count(self::$captureStack) - 1;
        self::$captureStack[$top] = $relation;
    }

    /**
     * Inspect the call stack to identify the relation method that invoked
     * hasOne / hasMany / belongsTo. Used as the cache key in $this->relations
     * so eager-loaded data is reused by direct method calls.
     */
    private function callingRelationName(): ?string
    {
        // Frame 0 = this helper, 1 = hasOne/hasMany/belongsTo, 2 = the relation method
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        return $frames[2]['function'] ?? null;
    }

    /**
     * Define a one-to-many relationship.
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): array
    {
        $foreignKey ??= $this->getForeignKey();
        $localKey ??= $this->primaryKey;

        if (self::inCaptureMode()) {
            self::capture(new Relation(Relation::HAS_MANY, $this, $related, $localKey, $foreignKey));
            return [];
        }

        $caller = $this->callingRelationName();
        if ($caller !== null && array_key_exists($caller, $this->relations)) {
            $cached = $this->relations[$caller];
            return is_array($cached) ? $cached : [];
        }

        $localValue = $this->$localKey;
        if ($localValue === null) {
            return [];
        }
        $result = $related::where($foreignKey, $localValue)->get();
        if ($caller !== null) {
            $this->relations[$caller] = $result;
        }
        return $result;
    }

    /**
     * Define an inverse one-to-many or one-to-one relationship.
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): ?Model
    {
        $relatedInstance = new $related();
        $foreignKey ??= $relatedInstance->getForeignKey();
        $ownerKey ??= $relatedInstance->primaryKey;

        if (self::inCaptureMode()) {
            self::capture(new Relation(Relation::BELONGS_TO, $this, $related, $foreignKey, $ownerKey));
            return null;
        }

        $caller = $this->callingRelationName();
        if ($caller !== null && array_key_exists($caller, $this->relations)) {
            $cached = $this->relations[$caller];
            return $cached instanceof Model ? $cached : null;
        }

        $foreignValue = $this->$foreignKey;
        if ($foreignValue === null) {
            return null;
        }
        $result = $related::where($ownerKey, $foreignValue)->first();
        if ($caller !== null) {
            $this->relations[$caller] = $result;
        }
        return $result;
    }

    /**
     * Define a one-to-one relationship.
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): ?Model
    {
        $foreignKey ??= $this->getForeignKey();
        $localKey ??= $this->primaryKey;

        if (self::inCaptureMode()) {
            self::capture(new Relation(Relation::HAS_ONE, $this, $related, $localKey, $foreignKey));
            return null;
        }

        $caller = $this->callingRelationName();
        if ($caller !== null && array_key_exists($caller, $this->relations)) {
            $cached = $this->relations[$caller];
            return $cached instanceof Model ? $cached : null;
        }

        $localValue = $this->$localKey;
        if ($localValue === null) {
            return null;
        }
        $result = $related::where($foreignKey, $localValue)->first();
        if ($caller !== null) {
            $this->relations[$caller] = $result;
        }
        return $result;
    }

    /**
     * Get the default foreign key name for this model
     *
     * @return string The foreign key name (e.g., 'user_id' for User model)
     */
    public function getForeignKey(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();
        return strtolower($class) . '_id';
    }

    /**
     * Fire an event if the event dispatcher is available
     *
     * Gracefully degrades if no dispatcher is registered (e.g., in tests or CLI
     * scripts that don't boot the full application).
     */
    protected static function fireEvent(\Echo\Framework\Event\EventInterface $event): \Echo\Framework\Event\EventInterface
    {
        try {
            $container = container();
            if ($container && $container->has(EventDispatcherInterface::class)) {
                return $container->get(EventDispatcherInterface::class)->dispatch($event);
            }
        } catch (\Throwable) {
            // Gracefully degrade — event dispatching should never break model operations
        }

        return $event;
    }

    /**
     * Bulk insert multiple records
     *
     * @param array $records Array of associative arrays with column => value pairs
     * @return bool True on success
     */
    public static function createBulk(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        $model = new static();
        $columns = array_keys($records[0]);
        foreach ($columns as $col) {
            self::validateIdentifier($col);
        }
        $placeholders = [];
        $values = [];

        foreach ($records as $record) {
            $placeholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            foreach ($columns as $col) {
                $values[] = $record[$col] ?? null;
            }
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $model->tableName,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        return db()->execute($sql, $values) !== false;
    }
}
