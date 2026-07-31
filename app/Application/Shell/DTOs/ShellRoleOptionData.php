<?php

namespace App\Application\Shell\DTOs;

final readonly class ShellRoleOptionData
{
    public function __construct(public string $slug, public string $name)
    {
    }
}
