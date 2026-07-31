<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class SeparationWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $settlements,
        public Collection $employees,
        public array $abilities,
        public array $settlementActions,
        public array $settlementCompensationVisibility,
    ) {}

    public function toView(): array { return get_object_vars($this); }
}
