<?php

namespace Echo\Framework\Database;

use Echo\Framework\Event\EventDispatcherInterface;
use Echo\Framework\Event\Model\ModelCreating;
use Echo\Framework\Event\Model\ModelCreated;
use Echo\Framework\Event\Model\ModelUpdating;
use Echo\Framework\Event\Model\ModelUpdated;
use Echo\Framework\Event\Model\ModelDeleting;
use Echo\Framework\Event\Model\ModelDeleted;
use Exception;
use InvalidArgumentException;
use PDO;
use RuntimeException;

abstract class Model implements ModelInterface
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

    public static function find(string $id): ?static
    {
        $class = static::class;
        try {
            $model = new $class($id);
            return $model->id !== null ? $model : null;
        } catch (Exception) {
            return null;
        }
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
            ->orderBy($this->orderBy)
            ->limit($limit)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);

        return $results ?: [];
    }

    /**
     * Specify relationships to eager load
     *
     * @param string ...$relations Relation method names to eager load
     * @return static
     */
    public static function with(string ...$relations): static
    {
        $class = static::class;
        $model = new $class();
        $model->eagerLoad = $relations;
        return $model;
    }

    /**
     * Add eager loading to an existing query
     */
    public function load(string ...$relations): static
    {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
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
            $this->loadRelations($models);
        }

        return $models;
    }

    /**
     * Load eager loaded relations for a collection of models
     */
    private function loadRelations(array &$models): void
    {
        foreach ($this->eagerLoad as $relation) {
            if (!method_exists($this, $relation)) {
                continue;
            }

            // Load relation for each model individually
            // Note: This is N+1. Batch optimization would require
            // analyzing foreign keys and consolidating queries.
            foreach ($models as $model) {
                $model->relations[$relation] = $model->$relation();
            }
        }
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
        return $this->attributes[$name] ?? null;
    }

    public function __isset($name): bool
    {
        return isset($this->attributes[$name]);
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
     * Define a one-to-many relationship
     *
     * @param string $related The related model class
     * @param string|null $foreignKey The foreign key on the related model
     * @param string|null $localKey The local key on this model
     * @return array Array of related models
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): array
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;

        $localValue = $this->$localKey;
        if ($localValue === null) {
            return [];
        }

        return $related::where($foreignKey, $localValue)->get();
    }

    /**
     * Define an inverse one-to-many or one-to-one relationship
     *
     * @param string $related The related model class
     * @param string|null $foreignKey The foreign key on this model
     * @param string|null $ownerKey The primary key on the related model
     * @return Model|null The related model or null
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): ?Model
    {
        $relatedInstance = new $related();
        $foreignKey = $foreignKey ?? $relatedInstance->getForeignKey();
        $ownerKey = $ownerKey ?? $relatedInstance->primaryKey;

        $foreignValue = $this->$foreignKey;
        if ($foreignValue === null) {
            return null;
        }

        return $related::where($ownerKey, $foreignValue)->first();
    }

    /**
     * Define a one-to-one relationship
     *
     * @param string $related The related model class
     * @param string|null $foreignKey The foreign key on the related model
     * @param string|null $localKey The local key on this model
     * @return Model|null The related model or null
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): ?Model
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;

        $localValue = $this->$localKey;
        if ($localValue === null) {
            return null;
        }

        return $related::where($foreignKey, $localValue)->first();
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
