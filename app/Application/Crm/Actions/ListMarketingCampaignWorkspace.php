<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\MarketingCampaignWorkspaceData;
use App\Domain\Crm\Services\CrmWorkspaceOptions;
use App\Domain\Crm\Services\MarketingWorkspaceRegister;
use App\Models\MarketingCampaign;
use App\Models\User;

final class ListMarketingCampaignWorkspace
{
    public function __construct(
        private readonly MarketingWorkspaceRegister $register,
        private readonly CrmWorkspaceOptions $options,
    ) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): MarketingCampaignWorkspaceData
    {
        return new MarketingCampaignWorkspaceData(
            campaigns: $this->register->campaigns($user, $filters),
            filters: $filters,
            companies: $this->options->companies($user),
            projects: $this->options->projects($user),
            summary: $this->register->campaignSummary($user),
            statuses: ['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed', 'archived' => 'Archived'],
            channels: ['digital' => 'Digital', 'print' => 'Print', 'outdoor' => 'Outdoor', 'referral' => 'Referral', 'channel_partner' => 'Channel partner', 'event' => 'Event', 'portal' => 'Portal', 'social' => 'Social', 'email' => 'Email', 'sms' => 'SMS', 'other' => 'Other'],
            canCreate: $user->can('create', MarketingCampaign::class),
        );
    }
}
