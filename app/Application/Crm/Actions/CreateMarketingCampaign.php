<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\CrmCommandData;
use App\Models\MarketingCampaign;
use App\Services\Crm\MarketingCampaignService;

final class CreateMarketingCampaign
{
    public function __construct(private readonly MarketingCampaignService $campaigns) {}

    public function execute(CrmCommandData $command): MarketingCampaign
    {
        return $this->campaigns->createCampaign($command->attributes, $command->actor, $command->request);
    }
}
