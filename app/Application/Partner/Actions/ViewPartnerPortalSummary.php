<?php

namespace App\Application\Partner\Actions;

use App\Application\Partner\Data\PartnerPortalSummaryData;
use App\Models\User;
use App\Services\Partner\PartnerDashboardService;

final class ViewPartnerPortalSummary
{
    public function __construct(private readonly PartnerDashboardService $dashboard) {}

    public function execute(User $actor, array $filters): PartnerPortalSummaryData
    {
        return new PartnerPortalSummaryData(
            summary: $this->dashboard->summaryFor($actor, (int) ($filters['limit'] ?? 10)),
            filters: $filters,
            leadStatuses: ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost', 'on_hold' => 'On Hold'],
            bookingStatuses: ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'],
            commissionStatuses: ['draft' => 'Draft', 'generated' => 'Generated', 'pending_approval' => 'Pending Approval', 'approved' => 'Approved', 'payroll_included' => 'Payroll Included', 'paid' => 'Paid'],
        );
    }
}
