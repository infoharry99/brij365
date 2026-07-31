<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\LeadActivityWorkspaceData;
use App\Domain\Crm\Services\CrmWorkspaceOptions;
use App\Domain\Crm\Services\MarketingWorkspaceRegister;
use App\Models\LeadActivity;
use App\Models\User;

final class ListLeadActivityWorkspace
{
    public function __construct(
        private readonly MarketingWorkspaceRegister $register,
        private readonly CrmWorkspaceOptions $options,
    ) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): LeadActivityWorkspaceData
    {
        return new LeadActivityWorkspaceData(
            activities: $this->register->activities($user, $filters),
            filters: $filters,
            projects: $this->options->projects($user),
            campaigns: $this->options->campaigns($user),
            leads: $this->register->leads($user),
            types: ['created' => 'Created', 'note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'site_visit' => 'Site visit', 'qualification' => 'Qualification', 'stage_change' => 'Stage change', 'campaign_response' => 'Campaign response', 'follow_up' => 'Follow-up', 'booking' => 'Booking'],
            canCreate: $user->can('create', LeadActivity::class),
        );
    }
}
