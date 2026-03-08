<?php

namespace Echo\Framework\Database;

interface ModelInterface
{
    public static function create(array $data): static|bool;
    public static function find(string $id): ?static;
    public static function where(string $field, string $operator = '=', ?string $value = null): static;
    public function orWhere(string $field, string $operator = '=', ?string $value = null): static;
    public function andWhere(string $field, string $operator = '=', ?string $value = null): static;
    public function whereRaw(string $sql, array $params = []): static;
    public function whereBetween(string $field, mixed $start, mixed $end): static;
    public function whereNull(string $field): static;
    public function whereNotNull(string $field): static;
    public function orderBy(string $column, string $direction = "ASC"): static;
    public function groupBy(string ...$columns): static;
    public function select(array $columns): static;
    public function refresh(): static;
    public function get(int $limit = 0): array;
    public function first(): ?static;
    public function last(): ?static;
    public function sql(int $limit = 0): array;
    public function save(): static;
    public function update(array $data): static;
    public function delete(): bool;
    public function count(string $column = '*'): int;
    public function max(string $column): mixed;
    public function getAttributes(): array;
    public function getTableName(): string;
    public function getId(): string|int|null;
}
