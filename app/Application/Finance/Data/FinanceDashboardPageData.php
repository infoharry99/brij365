<?php

namespace App\Application\Finance\Data;

use Illuminate\Support\Collection;

final readonly class FinanceDashboardPageData
{
    public function __construct(
        public array $dashboard,
        public array $filters,
        public Collection $companies,
        public Collection $projects,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
