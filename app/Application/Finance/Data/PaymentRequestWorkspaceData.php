<?php

namespace App\Application\Finance\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class PaymentRequestWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $paymentRequests,
        public array $filters,
        public Collection $projects,
        public Collection $bookings,
        public Collection $customers,
        public array $statuses,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), ['canCreatePaymentRequest' => $this->abilities['canCreatePaymentRequest'] ?? false]);
    }
}
