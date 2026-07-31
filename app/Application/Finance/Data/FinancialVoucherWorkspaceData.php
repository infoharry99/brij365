<?php

namespace App\Application\Finance\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class FinancialVoucherWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $vouchers,
        public array $filters,
        public Collection $companies,
        public Collection $projects,
        public array $statuses,
        public array $voucherTypes,
        public array $lineTypes,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), ['canCreateVoucher' => $this->abilities['canCreateVoucher'] ?? false]);
    }
}
