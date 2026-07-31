<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeePolicyAcknowledgement;
use App\Services\Hr\EmployeePolicyAcknowledgementService;

final class AcknowledgeEmployeePolicy
{
    public function __construct(private readonly EmployeePolicyAcknowledgementService $service) {}

    public function execute(HrCommandData $c): EmployeePolicyAcknowledgement
    {
        return $this->service->acknowledge($c->attributes, $c->actor, $c->request);
    }
}
