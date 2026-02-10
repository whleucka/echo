<?php

namespace Echo\Framework\Database;

interface ModelInterface
{
    public static function create(array $data);
    public static function find(string $id);
    public static function where(string $field, string $operator = '=', ?string $value = null);
    public function orWhere(string $field, string $operator = '=', ?string $value = null);
    public function andWhere(string $field, string $operator = '=', ?string $value = null);
    public function orderBy(string $column, string $direction = "ASC"): ModelInterface;
    public function refresh(): ModelInterface;
    public function get(int $limit = 0): null|array|static;
    public function first(): ?static;
    public function last(): ?static;
    public function sql(int $limit = 1): array;
    public function save(): ModelInterface;
    public function update(array $data): ModelInterface;
    public function delete(): bool;
}
