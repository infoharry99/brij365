<?php

namespace App\Application\Identity\DTOs;

final readonly class ThemePreferenceData
{
    public function __construct(public string $theme)
    {
    }
}
