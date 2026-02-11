<?php

namespace Echo\Framework\Database;

use Exception;
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
        }
    }

    public static function create(array $data): static|bool
    {
        $class = get_called_class();
        $model = new $class();
        $result = $model->qb
            ->insert($data)
            ->into($model->tableName)
            ->params(array_values($data))
            ->execute();
        if ($result && $model->autoIncrement) {
            $id = db()->lastInsertId();
            return self::find($id);
        } elseif ($result && !$model->autoIncrement) {
            return true;
        }
        return false;
    }

    public static function find(string $id): ?static
    {
        $class = get_called_class();
        $model = new $class();
        try {
            $result = new $model($id);
            return $result;
        } catch (Exception) {
            return null;
        }
    }

    public static function where(string $field, string $operator = '=', ?string $value = null): static
    {
        $class = get_called_class();
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

    public function orWhere(string $field, string $operator = '=', ?string $value = null): Model
    {
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

    public function andWhere(string $field, string $operator = '=', ?string $value = null): Model
    {
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
        $this->where[] = "($field IS NOT NULL)";
        return $this;
    }

    public function orderBy(string $column, string $direction = "ASC"): Model
    {
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
        $class = get_called_class();
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

    public function refresh(): Model
    {
        $this->loadAttributes($this->id);
        return $this;
    }

    public function get(int $limit = 0): null|array|static
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
            return null;
        }

        // Hydrate results
        $models = array_map(fn($row) => static::hydrate($row), $results);

        // Perform eager loading if specified
        if (!empty($this->eagerLoad)) {
            $this->loadRelations($models);
        }

        if (count($models) === 1) {
            return $models[0];
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

            // Determine the relationship type by calling it on a model
            $testModel = $models[0];
            $relationInfo = $this->getRelationInfo($testModel, $relation);

            if ($relationInfo === null) {
                continue;
            }

            // Batch load the related models
            $this->eagerLoadRelation($models, $relation, $relationInfo);
        }
    }

    /**
     * Get relationship info by inspecting the relation method
     */
    private function getRelationInfo(Model $model, string $relation): ?array
    {
        // Get the primary keys from all models for batching
        $method = new \ReflectionMethod($model, $relation);
        $returnType = $method->getReturnType();

        if ($returnType === null) {
            return null;
        }

        $typeName = $returnType->getName();

        // Check if it's a hasMany (returns array) or belongsTo/hasOne (returns Model|null)
        if ($typeName === 'array') {
            return ['type' => 'hasMany'];
        } elseif (is_subclass_of($typeName, Model::class) || $typeName === Model::class) {
            return ['type' => 'belongsTo'];
        }

        return null;
    }

    /**
     * Eager load a specific relation for all models
     */
    private function eagerLoadRelation(array &$models, string $relation, array $relationInfo): void
    {
        // Collect primary keys
        $primaryKeys = [];
        foreach ($models as $model) {
            $pk = $model->{$model->primaryKey};
            if ($pk !== null) {
                $primaryKeys[] = $pk;
            }
        }

        if (empty($primaryKeys)) {
            return;
        }

        // Call the relation method on first model to determine the related class and keys
        // This is a simplified approach - for complex eager loading, more sophisticated logic would be needed
        foreach ($models as $model) {
            $model->relations[$relation] = $model->$relation();
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
        $class = get_called_class();
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
        $class = get_called_class();
        $model = new $class();
        return $model->max($column);
    }

    public function last(): ?static
    {
        $results = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->orderBy($this->orderBy)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_OBJ);

        if ($results) {
            return static::hydrate(end($results));
        }
        return null;
    }

    public function sql(int $limit = 1): array
    {
        $qb = $this->qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where($this->where)
            ->orWhere($this->orWhere)
            ->groupBy($this->groupBy)
            ->orderBy($this->orderBy)
            ->params($this->params);
        return ["query" => $qb->getQuery(), "params" => $qb->getQueryParams()];
    }

    public function save(): Model
    {
        $key = $this->primaryKey;
        $params = [...array_values($this->attributes), $this->id];
        $result = $this->qb
            ->update($this->attributes)
            ->table($this->tableName)
            ->where(["$key = ?"])
            ->params($params)
            ->execute();
        if ($result) {
            $this->loadAttributes($this->id);
        }
        return $this;
    }

    public function update(array $data): Model
    {
        $key = $this->primaryKey;
        $params = [...array_values($data), $this->id];
        $result = $this->qb
            ->update($data)
            ->table($this->tableName)
            ->where(["$key = ?"])
            ->params($params)
            ->execute();
        if ($result) {
            $this->loadAttributes($this->id);
        }
        return $this;
    }

    public function delete(): bool
    {
        $key = $this->primaryKey;
        $result = $this->qb
            ->delete()
            ->from($this->tableName)
            ->where(["$key = ?"], $this->id)
            ->execute();
        return (bool) $result;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function __set($name, $value)
    {
        return $this->attributes[$name] = $value;
    }

    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Hydrate a model instance from data without additional queries
     */
    protected static function hydrate(object $data): static
    {
        $class = get_called_class();
        $model = new $class();
        $model->attributes = (array) $data;
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

        return $related::where($foreignKey, $localValue)->get() ?? [];
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
     * Bulk insert multiple records
     *
     * @param array $records Array of associative arrays with column => value pairs
     * @return bool True on success
     */
    public static function insert(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        $model = new static();
        $columns = array_keys($records[0]);
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
