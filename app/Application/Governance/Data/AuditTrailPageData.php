<?php

namespace App\Application\Governance\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class AuditTrailPageData
{
    public function __construct(
        public LengthAwarePaginator $events,
        public array $filters,
        public Collection $eventTypes,
        public Collection $auditableTypes,
        public Collection $users,
        public array $requestMethods,
    ) {}
    public function toView(): array { return get_object_vars($this); }
}
