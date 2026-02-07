<?php

namespace Echo\Framework\Admin;

class ModuleState
{
    public function __construct(private string $moduleKey) {}

    public function getPage(): int
    {
        return $this->get('page', 1);
    }

    public function setPage(int $page): void
    {
        $this->set('page', $page);
    }

    public function getPerPage(int $default): int
    {
        return $this->get('per_page', $default);
    }

    public function setPerPage(int $count): void
    {
        $this->set('per_page', $count);
    }

    public function getOrderBy(string $default): string
    {
        return $this->get('order_by', $default);
    }

    public function setOrderBy(string $col): void
    {
        $this->set('order_by', $col);
    }

    public function getSort(string $default): string
    {
        return $this->get('sort', $default);
    }

    public function setSort(string $dir): void
    {
        $this->set('sort', $dir);
    }

    public function getActiveFilterLink(): int
    {
        return $this->get('filter_link', 0);
    }

    public function setActiveFilterLink(int $idx): void
    {
        $this->set('filter_link', $idx);
    }

    public function getFilter(string $key): mixed
    {
        $filters = $this->getFilters();
        return $filters[$key] ?? null;
    }

    public function setFilter(string $key, mixed $value): void
    {
        $filters = $this->getFilters();
        $filters[$key] = $value;
        $this->set('filters', $filters);
    }

    public function removeFilter(string $key): void
    {
        $filters = $this->getFilters();
        unset($filters[$key]);
        $this->set('filters', $filters);
    }

    public function getFilters(): array
    {
        return $this->get('filters', []);
    }

    public function clearFilters(): void
    {
        $this->set('filters', []);
        $this->setPage(1);
    }

    public function hasFilters(): bool
    {
        return !empty($this->getFilters());
    }

    private function get(string $key, mixed $default): mixed
    {
        $data = session()->get($this->moduleKey);
        return $data[$key] ?? $default;
    }

    private function set(string $key, mixed $value): void
    {
        $data = session()->get($this->moduleKey) ?? [];
        $data[$key] = $value;
        session()->set($this->moduleKey, $data);
    }
}
