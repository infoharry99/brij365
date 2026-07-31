<?php

namespace App\Application\Buyer\Actions;

use App\Application\Buyer\Data\BuyerPortalWorkspaceData;
use App\Domain\Buyer\Services\BuyerPortalRegister;
use App\Models\User;

final class ListBuyerServiceTickets
{
    public function __construct(private readonly BuyerPortalRegister $register) {}
    public function execute(User $actor, array $filters): BuyerPortalWorkspaceData
    {
        return new BuyerPortalWorkspaceData('service-tickets', $this->register->customer($actor), $this->register->tickets($actor, $filters), $filters,
            ['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'],
            ['defect' => 'Defect', 'maintenance' => 'Maintenance', 'billing' => 'Billing', 'documentation' => 'Documentation', 'society' => 'Society', 'other' => 'Other'],
            ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']);
    }
}
