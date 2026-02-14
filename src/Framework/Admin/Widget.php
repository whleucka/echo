<?php

namespace Echo\Framework\Admin;

abstract class Widget
{
    protected string $id;
    protected string $title;
    protected string $icon = '';
    protected string $template;
    protected int $width = 6;
    protected int $refreshInterval = 0;
    protected int $cacheTtl = 0;
    protected int $priority = 100; // Lower = higher priority (displayed first)

    /**
     * Get the widget data
     */
    abstract public function getData(): array;

    /**
     * Render the widget
     */
    public function render(): string
    {
        if ($this->cacheTtl > 0) {
            $cacheKey = 'widget_' . $this->id;
            $cached = $this->getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $data = $this->getData();

        $html = twig()->render($this->template, [
            'widget' => [
                'id' => $this->id,
                'title' => $this->title,
                'icon' => $this->icon,
                'width' => $this->width,
                'refresh_interval' => $this->refreshInterval,
            ],
            'data' => $data,
        ]);

        if ($this->cacheTtl > 0) {
            $this->setCache($cacheKey, $html, $this->cacheTtl);
        }

        return $html;
    }

    /**
     * Get widget ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get widget title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get widget icon
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Get widget width (Bootstrap grid columns 1-12)
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Get refresh interval in seconds
     */
    public function getRefreshInterval(): int
    {
        return $this->refreshInterval;
    }

    /**
     * Get widget priority (lower = displayed first)
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get cache from file
     */
    private function getCache(string $key): ?string
    {
        $cacheDir = config('paths.cache') ?? sys_get_temp_dir();
        $cacheFile = $cacheDir . '/widget_' . md5($key) . '.cache';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $content = file_get_contents($cacheFile);
        $data = json_decode($content, true);

        if ($data === null || !isset($data['expires']) || $data['expires'] < time()) {
            @unlink($cacheFile);
            return null;
        }

        return $data['value'];
    }

    /**
     * Set cache to file
     */
    private function setCache(string $key, string $value, int $ttl): void
    {
        $cacheDir = config('paths.cache') ?? sys_get_temp_dir();

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . '/widget_' . md5($key) . '.cache';

        $data = [
            'expires' => time() + $ttl,
            'value' => $value,
        ];

        file_put_contents($cacheFile, json_encode($data));
    }
}
