<?php

namespace App\Application\Partner\Data;

final readonly class PartnerPortalSummaryData
{
    public function __construct(
        public array $summary,
        public array $filters,
        public array $leadStatuses,
        public array $bookingStatuses,
        public array $commissionStatuses,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
