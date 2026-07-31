<?php

namespace App\Application\Finance\Data;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class GstReturnPeriodWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $periods,
        public array $filters,
        public array $statuses,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), ['canPreparePeriod' => $this->abilities['canPreparePeriod'] ?? false]);
    }
}
