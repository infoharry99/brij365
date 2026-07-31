<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\CrmCommandData;
use App\Models\LeadActivity;
use App\Services\Crm\MarketingCampaignService;

final class RecordLeadActivity
{
    public function __construct(private readonly MarketingCampaignService $activities) {}

    public function execute(CrmCommandData $command): LeadActivity
    {
        return $this->activities->createLeadActivity($command->attributes, $command->actor, $command->request);
    }
}
