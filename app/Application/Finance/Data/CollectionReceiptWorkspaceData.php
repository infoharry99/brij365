<?php

namespace App\Application\Finance\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class CollectionReceiptWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $receipts,
        public array $filters,
        public Collection $bookings,
        public Collection $projects,
        public Collection $customers,
        public array $statuses,
        public array $paymentModes,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), [
            'canCreateReceipt' => $this->abilities['canCreateReceipt'] ?? false,
        ]);
    }
}
