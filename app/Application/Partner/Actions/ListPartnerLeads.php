<?php

namespace App\Application\Partner\Actions;

use App\Application\Partner\Data\PartnerPortalWorkspaceData;
use App\Domain\Partner\Services\PartnerPortalRegister;
use App\Models\User;

final class ListPartnerLeads
{
    public function __construct(private readonly PartnerPortalRegister $register) {}
    public function execute(User $actor, array $filters): PartnerPortalWorkspaceData
    {
        return new PartnerPortalWorkspaceData(
            'leads',
            $this->register->leads($actor, $filters),
            $filters,
            ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost', 'on_hold' => 'On Hold'],
            ['New' => 'New', 'Qualified' => 'Qualified', 'Nurture' => 'Nurture', 'Disqualified' => 'Disqualified', 'Site Visit Planned' => 'Site Visit Planned', 'Site Visit Scheduled' => 'Site Visit Scheduled', 'Site Visit Done' => 'Site Visit Done', 'Follow-up' => 'Follow-up', 'Negotiation' => 'Negotiation', 'Booked' => 'Booked', 'Lost' => 'Lost'],
        );
    }
}
