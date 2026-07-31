<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ExitInterviewWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $interviews,
        public array $summary,
        public Collection $employees,
        public Collection $settlements,
        public array $abilities,
        public array $interviewActions,
    ) {}

    public function toView(): array { return get_object_vars($this); }
}
