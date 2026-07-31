<?php

namespace App\Application\Buyer\Data;

final readonly class BuyerPortalSummaryData
{
    public function __construct(
        public array $summary,
        public array $categories,
        public array $priorities,
        public array $ticketStatuses,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
