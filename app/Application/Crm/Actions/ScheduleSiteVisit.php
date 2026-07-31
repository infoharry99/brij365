<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\CrmCommandData;
use App\Models\SiteVisit;
use App\Services\Crm\LeadEngagementService;

final class ScheduleSiteVisit
{
    public function __construct(private readonly LeadEngagementService $engagement) {}

    public function execute(CrmCommandData $command): SiteVisit
    {
        return $this->engagement->scheduleSiteVisit($command->attributes, $command->actor, $command->request);
    }
}
