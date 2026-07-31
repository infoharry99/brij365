<?php

namespace App\Application\Shell\DTOs;

final readonly class ShellProjectOptionData
{
    public function __construct(public int $id, public string $code, public string $name)
    {
    }
}
