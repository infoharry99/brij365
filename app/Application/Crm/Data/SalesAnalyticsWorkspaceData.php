<?php

namespace App\Application\Crm\Data;

use Illuminate\Support\Collection;

final readonly class SalesAnalyticsWorkspaceData
{
    /** @param array<string, mixed> $filters @param array<string, mixed> $report */
    public function __construct(public array $filters, public Collection $projects, public array $report) {}
}
