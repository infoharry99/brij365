<?php

namespace App\Application\Partner\Data;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class PartnerPortalWorkspaceData
{
    public function __construct(
        public string $section,
        public LengthAwarePaginator $records,
        public array $filters,
        public array $statuses,
        public array $stages = [],
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
