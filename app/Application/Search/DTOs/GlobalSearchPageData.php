<?php

namespace App\Application\Search\DTOs;

final readonly class GlobalSearchPageData
{
    /** @param list<SearchGroupData> $groups */
    public function __construct(
        public string $query,
        public array $groups,
        public int $total,
    ) {
    }
}
