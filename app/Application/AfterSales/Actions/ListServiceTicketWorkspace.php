<?php

namespace App\Application\AfterSales\Actions;

use App\Application\AfterSales\Data\ServiceTicketWorkspaceData;
use App\Domain\AfterSales\Services\AfterSalesRegister;
use App\Models\ServiceTicket;
use App\Models\User;

final class ListServiceTicketWorkspace
{
    public function __construct(private readonly AfterSalesRegister $register) {}

    public function execute(User $actor, array $filters): ServiceTicketWorkspaceData
    {
        $buyer = $this->register->isBuyerPortalUser($actor);

        return new ServiceTicketWorkspaceData(
            tickets: $this->register->tickets($actor, $filters),
            filters: $filters,
            projects: $buyer ? collect() : $this->register->projects($actor),
            bookings: $this->register->bookings($actor),
            customers: $buyer ? collect() : $this->register->customers($actor),
            assignees: $buyer ? collect() : $this->register->assignees($actor),
            statuses: ['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'],
            priorities: ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'],
            categories: ['defect' => 'Defect', 'maintenance' => 'Maintenance', 'billing' => 'Billing', 'documentation' => 'Documentation', 'society' => 'Society', 'other' => 'Other'],
            sources: $buyer ? ['portal' => 'Portal'] : ['phone' => 'Phone', 'email' => 'Email', 'internal' => 'Internal', 'portal' => 'Portal'],
            abilities: ['canCreateTicket' => $actor->can('create', ServiceTicket::class)],
            isBuyerPortalUser: $buyer,
        );
    }
}
