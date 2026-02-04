<?php

namespace Echo\Framework\Admin;

class WidgetRegistry
{
    private static array $widgets = [];

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
    }

    /**
     * Get a widget by ID
     */
    public static function get(string $id): ?Widget
    {
        if (!isset(self::$widgets[$id])) {
            return null;
        }

        $class = self::$widgets[$id];
        return new $class();
    }

    /**
     * Get all registered widgets
     */
    public static function all(): array
    {
        $widgets = [];
        foreach (self::$widgets as $id => $class) {
            $widgets[$id] = new $class();
        }
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
    }

    /**
     * Clear all registered widgets
     */
    public static function clear(): void
    {
        self::$widgets = [];
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
