<?php

namespace App\Application\Buyer\Data;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class BuyerPortalWorkspaceData
{
    public function __construct(
        public string $section,
        public ?Customer $customer,
        public LengthAwarePaginator $records,
        public array $filters,
        public array $statuses = [],
        public array $categories = [],
        public array $priorities = [],
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
