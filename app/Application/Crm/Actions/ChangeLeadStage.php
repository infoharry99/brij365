<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\CrmCommandData;
use App\Models\Lead;
use App\Services\Crm\LeadService;

final class ChangeLeadStage
{
    public function __construct(private readonly LeadService $leads) {}

    public function execute(Lead $lead, CrmCommandData $command): Lead
    {
        return $this->leads->updateStage($lead, $command->attributes, $command->actor, $command->request);
    }
}
