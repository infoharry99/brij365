<?php

namespace App\Application\Hr\Data;

final readonly class PeopleWorkspaceLinkData
{
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $route,
    ) {}
}
