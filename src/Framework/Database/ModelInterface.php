<?php

namespace Echo\Framework\Database;

interface ModelInterface
{
    public static function create(array $data): static|bool;
    public static function find(string $id): ?static;
    public static function findOrFail(string $id): static;
    public static function where(string $field, string $operator = '=', ?string $value = null): static;
    public function orWhere(string $field, string $operator = '=', ?string $value = null): static;
    public function andWhere(string $field, string $operator = '=', ?string $value = null): static;
    public function whereRaw(string $sql, array $params = []): static;
    public function whereBetween(string $field, mixed $start, mixed $end): static;
    public function whereNull(string $field): static;
    public function whereNotNull(string $field): static;
    public function orderBy(string $column, string $direction = "ASC"): static;
    public function latest(string $column = 'created_at'): static;
    public function oldest(string $column = 'created_at'): static;
    public function groupBy(string ...$columns): static;
    public function select(array $columns): static;
    public function refresh(): static;
    public function get(int $limit = 0): array;
    public function first(): ?static;
    public function firstOrFail(): static;
    public function last(): ?static;
    public function pluck(string $column): array;
    public function keyBy(string $column): array;
    public function value(string $column): mixed;
    public function exists(): bool;
    public function doesntExist(): bool;
    public function sql(int $limit = 0): array;
    public function save(): static;
    public function update(array $data): static;
    public function delete(): bool;
    public function count(string $column = '*'): int;
    public function min(string $column): mixed;
    public function max(string $column): mixed;
    public function sum(string $column): mixed;
    public function avg(string $column): mixed;
    public function getAttributes(): array;
    public function getTableName(): string;
    public function getId(): string|int|null;
}
