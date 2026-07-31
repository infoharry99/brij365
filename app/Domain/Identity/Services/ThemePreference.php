<?php

namespace App\Domain\Identity\Services;

use Illuminate\Contracts\Session\Session;

final class ThemePreference
{
    private const SESSION_KEY = 'builder360.theme';

    public function __construct(private readonly Session $session)
    {
    }

    public function current(): string
    {
        $theme = $this->session->get(self::SESSION_KEY, 'light');

        return in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
    }

    public function store(string $theme): void
    {
        $this->session->put(self::SESSION_KEY, $theme);
    }
}
