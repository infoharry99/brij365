<?php

namespace App\Application\Shell\DTOs;

final readonly class ShellNavigationItemData
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $iconClass,
        public ?string $url,
        public bool $active,
    ) {
    }
}
