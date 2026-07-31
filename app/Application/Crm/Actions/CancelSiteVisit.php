<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\CrmCommandData;
use App\Models\SiteVisit;
use App\Services\Crm\LeadEngagementService;

final class CancelSiteVisit
{
    public function __construct(private readonly LeadEngagementService $engagement) {}

    public function execute(SiteVisit $visit, CrmCommandData $command): SiteVisit
    {
        return $this->engagement->cancelSiteVisit($visit, $command->attributes, $command->actor, $command->request);
    }
}
