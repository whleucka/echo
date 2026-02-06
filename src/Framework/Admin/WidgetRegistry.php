<?php

namespace Echo\Framework\Admin;

class WidgetRegistry
{
    private static array $widgets = [];
    private static array $instances = [];

    /**
     * Register a widget
     */
    public static function register(string $id, string $class): void
    {
        if (!is_subclass_of($class, Widget::class)) {
            throw new \InvalidArgumentException(
                "Widget class must extend " . Widget::class
            );
        }

        self::$widgets[$id] = $class;
        // Clear cached instance if re-registering
        unset(self::$instances[$id]);
    }

    /**
     * Get a widget by ID (lazy instantiation)
     */
    public static function get(string $id): ?Widget
    {
        if (!isset(self::$widgets[$id])) {
            return null;
        }

        // Lazy instantiation - only create when first requested
        if (!isset(self::$instances[$id])) {
            $class = self::$widgets[$id];
            self::$instances[$id] = new $class();
        }

        return self::$instances[$id];
    }

    /**
     * Get all registered widgets sorted by priority (lazy instantiation)
     */
    public static function all(): array
    {
        $widgets = [];
        foreach (self::$widgets as $id => $class) {
            $widgets[$id] = self::get($id);
        }

        // Sort by priority (lower = first)
        uasort($widgets, fn(Widget $a, Widget $b) => $a->getPriority() <=> $b->getPriority());

        return $widgets;
    }

    /**
     * Get widget IDs
     */
    public static function getIds(): array
    {
        return array_keys(self::$widgets);
    }

    /**
     * Check if a widget is registered
     */
    public static function has(string $id): bool
    {
        return isset(self::$widgets[$id]);
    }

    /**
     * Unregister a widget
     */
    public static function unregister(string $id): void
    {
        unset(self::$widgets[$id]);
        unset(self::$instances[$id]);
    }

    /**
     * Clear all registered widgets
     */
    public static function clear(): void
    {
        self::$widgets = [];
        self::$instances = [];
    }

    /**
     * Get widgets for a specific user (future: per-user configuration)
     */
    public static function getForUser(?int $userId): array
    {
        return self::all();
    }

    /**
     * Render all widgets
     */
    public static function renderAll(): string
    {
        $html = '';
        foreach (self::all() as $widget) {
            $html .= $widget->render();
        }
        return $html;
    }
}
