<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\CrmCommandData;
use App\Models\MarketingCampaign;
use App\Services\Crm\MarketingCampaignService;

final class ChangeMarketingCampaignStatus
{
    public function __construct(private readonly MarketingCampaignService $campaigns) {}

    public function execute(MarketingCampaign $campaign, CrmCommandData $command): MarketingCampaign
    {
        return $this->campaigns->updateStatus($campaign, $command->attributes, $command->actor, $command->request);
    }
}
