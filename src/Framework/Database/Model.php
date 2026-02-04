<?php

namespace Echo\Framework\Database;

use Echo\Interface\Database\Model as DatabaseModel;
use Exception;
use PDO;

class Model implements DatabaseModel
{
    protected string $primary_key = "id";
    protected bool $auto_increment = true;
    protected array $columns = ["*"];
    protected QueryBuilder $qb;
    private array $where = [];
    private array $or_where = [];
    private array $order_by = [];
    private array $params = [];
    private array $attributes = [];
    private array $valid_operators = [
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

    public function __construct(private string $table_name, private ?string $id = null)
    {
        // Initialize the query builder
        $this->qb = new QueryBuilder();

        if (!is_null($id)) {
            $this->loadAttributes($id);
        }
    }

    private function loadAttributes(string $id): void
    {
        $key = $this->primary_key;
        $result = $this->qb
            ->select($this->columns)
            ->from($this->table_name)
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
            ->into($model->table_name)
            ->params(array_values($data))
            ->execute();
        if ($result && $model->auto_increment) {
            $id = db()->lastInsertId();
            return self::find($id);
        } elseif ($result && !$model->auto_increment) {
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
        if (!in_array(strtolower($operator), $model->valid_operators)) {
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
        if (!in_array(strtolower($operator), $this->valid_operators)) {
            $value = $operator;
            $operator = "=";
        }
        // Add the where clause and params
        $this->or_where[] = "($field $operator ?)";
        $this->params[] = $value;
        return $this;
    }

    public function andWhere(string $field, string $operator = '=', ?string $value = null): Model
    {
        // Default operator is =
        if (!in_array(strtolower($operator), $this->valid_operators)) {
            $value = $operator;
            $operator = "=";
        }
        // Add the where clause and params
        $this->where[] = "($field $operator ?)";
        $this->params[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = "ASC"): Model
    {
        $this->order_by[] = "$column $direction";
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
            ->from($this->table_name)
            ->where($this->where)
            ->orWhere($this->or_where)
            ->orderBy($this->order_by)
            ->limit($limit)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_OBJ);

        if (!$results) {
            return null;
        }

        if (count($results) === 1) {
            return static::hydrate($results[0]);
        }

        return array_map(fn($row) => static::hydrate($row), $results);
    }

    public function first(): ?static
    {
        $results = $this->qb
            ->select($this->columns)
            ->from($this->table_name)
            ->where($this->where)
            ->orWhere($this->or_where)
            ->orderBy($this->order_by)
            ->limit(1)
            ->params($this->params)
            ->execute()
            ->fetchAll(PDO::FETCH_OBJ);

        if ($results) {
            return static::hydrate($results[0]);
        }
        return null;
    }

    public function last(): ?static
    {
        $results = $this->qb
            ->select($this->columns)
            ->from($this->table_name)
            ->where($this->where)
            ->orWhere($this->or_where)
            ->orderBy($this->order_by)
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
            ->from($this->table_name)
            ->where($this->where)
            ->orWhere($this->or_where)
            ->orderBy($this->order_by)
            ->params($this->params);
        return ["query" => $qb->getQuery(), "params" => $qb->getQueryParams()];
    }

    public function save(): Model
    {
        $key = $this->primary_key;
        $params = [...array_values($this->attributes), $this->id];
        $result = $this->qb
            ->update($this->attributes)
            ->table($this->table_name)
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
        $key = $this->primary_key;
        $params = [...array_values($data), $this->id];
        $result = $this->qb
            ->update($data)
            ->table($this->table_name)
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
        $key = $this->primary_key;
        $result = $this->qb
            ->delete()
            ->from($this->table_name)
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
        $model->id = $data->{$model->primary_key} ?? null;
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
        $localKey = $localKey ?? $this->primary_key;

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
        $ownerKey = $ownerKey ?? $relatedInstance->primary_key;

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
        $localKey = $localKey ?? $this->primary_key;

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
            $model->table_name,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        return db()->execute($sql, $values) !== false;
    }
}
