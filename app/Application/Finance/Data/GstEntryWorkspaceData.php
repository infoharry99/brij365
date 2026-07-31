<?php

namespace App\Application\Finance\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class GstEntryWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $entries,
        public array $filters,
        public Collection $projects,
        public array $statuses,
        public array $transactionTypes,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), ['canCreateEntry' => $this->abilities['canCreateEntry'] ?? false]);
    }
}
