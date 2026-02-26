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
            qb()->table('users')
                ->where('id', user()->id)
                ->update(['theme' => $enabled ? 'dark' : 'light']);
        }
    }

    public function toggle(): void
    {
        $this->setDarkMode(!$this->isDarkMode());
    }
}
