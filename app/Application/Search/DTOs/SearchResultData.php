<?php

namespace App\Application\Search\DTOs;

final readonly class SearchResultData
{
    public function __construct(
        public string $key,
        public string $title,
        public string $subtitle,
        public string $url,
        public string $icon,
    ) {
    }
}
