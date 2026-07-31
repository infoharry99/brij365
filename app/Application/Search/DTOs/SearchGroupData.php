<?php

namespace App\Application\Search\DTOs;

final readonly class SearchGroupData
{
    /** @param list<SearchResultData> $results */
    public function __construct(
        public string $key,
        public string $label,
        public array $results,
    ) {
    }
}
