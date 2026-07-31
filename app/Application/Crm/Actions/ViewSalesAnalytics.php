<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\SalesAnalyticsWorkspaceData;
use App\Domain\Crm\Services\CrmWorkspaceOptions;
use App\Domain\Crm\Services\SalesAnalyticsReport;
use App\Models\User;

final class ViewSalesAnalytics
{
    public function __construct(private readonly SalesAnalyticsReport $report, private readonly CrmWorkspaceOptions $options) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): SalesAnalyticsWorkspaceData
    {
        return new SalesAnalyticsWorkspaceData($filters, $this->options->projects($user), $this->report->for($user, $filters));
    }
}
