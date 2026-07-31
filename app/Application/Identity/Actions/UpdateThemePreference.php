<?php

namespace App\Application\Identity\Actions;

use App\Application\Identity\DTOs\ThemePreferenceData;
use App\Domain\Identity\Services\ThemePreference;

final class UpdateThemePreference
{
    public function __construct(private readonly ThemePreference $preference)
    {
    }

    public function handle(ThemePreferenceData $data): void
    {
        $this->preference->store($data->theme);
    }
}
