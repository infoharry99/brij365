<?php

namespace App\Application\Scoring\DTOs;

final readonly class LogicCenterSectionData
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $icon,
        public string $url,
        public bool $active,
    ) {
    }
}
