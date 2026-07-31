<?php

namespace App\Application\Buyer\Actions;

use App\Application\Buyer\Data\BuyerPortalSummaryData;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Buyer\BuyerPortalSummaryService;
use Illuminate\Http\Request;

final class ViewBuyerPortalSummary
{
    public function __construct(
        private readonly BuyerPortalSummaryService $summary,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, Request $request): BuyerPortalSummaryData
    {
        $summary = $this->summary->summaryFor($actor);

        $this->audit->record($actor, 'buyer.portal_summary.viewed', 'Viewed buyer portal summary', null, [
            'customer_id' => $summary['customer']['id'] ?? null,
            'bookings_count' => $summary['bookings_count'],
            'open_tickets_count' => $summary['open_tickets_count'],
            'documents_count' => $summary['documents_count'],
        ], $request);

        return new BuyerPortalSummaryData(
            summary: $summary,
            categories: ['defect' => 'Defect', 'maintenance' => 'Maintenance', 'billing' => 'Billing', 'documentation' => 'Documentation', 'society' => 'Society', 'other' => 'Other'],
            priorities: ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'],
            ticketStatuses: ['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'],
        );
    }
}
