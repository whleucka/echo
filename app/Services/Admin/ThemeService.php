<?php

namespace App\Services\Admin;

class ThemeService
{
    private const SESSION_KEY = "dark_mode";

    public function isDarkMode(): bool
    {
        return (bool) session()->get(self::SESSION_KEY);
    }

    public function setDarkMode(bool $enabled): void
    {
        session()->set(self::SESSION_KEY, $enabled);
        if (user()) {
            $theme = $enabled ? 'dark' : 'light';
            qb()->update(['theme' => $theme])
                ->params([$theme])
                ->table('users')
                ->where(['id = ?'], user()->id)
                ->execute();
        }
    }

    public function toggle(): void
    {
        $this->setDarkMode(!$this->isDarkMode());
    }
}
