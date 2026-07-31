<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\CrmCommandData;
use App\Models\Lead;
use App\Services\Crm\LeadService;

final class CreateLead
{
    public function __construct(private readonly LeadService $leads) {}

    public function execute(CrmCommandData $command): Lead
    {
        return $this->leads->create($command->attributes, $command->actor, $command->request);
    }
}
