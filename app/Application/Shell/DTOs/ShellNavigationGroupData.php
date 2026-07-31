<?php

namespace App\Application\Shell\DTOs;

final readonly class ShellNavigationGroupData
{
    /** @param list<ShellNavigationItemData> $items */
    public function __construct(public string $label, public array $items)
    {
    }
}
