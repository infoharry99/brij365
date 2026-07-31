<?php

namespace App\Application\Payroll\Data;

use App\Models\EmployeeTaxProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class EmployeeTaxProfileReviewWorkspaceData
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $profiles,
        public array $filters,
        public ?EmployeeTaxProfile $selectedProfile = null,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'taxProfiles' => $this->profiles,
            'filters' => $this->filters,
            'selectedTaxProfile' => $this->selectedProfile,
        ];
    }
}
