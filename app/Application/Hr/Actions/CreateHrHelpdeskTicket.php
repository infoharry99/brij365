<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\HrHelpdeskTicket;
use App\Services\Hr\EmployeeOperationsService;

final class CreateHrHelpdeskTicket
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(HrCommandData $c): HrHelpdeskTicket
    {
        return $this->service->createHelpdeskTicket($c->attributes, $c->actor, $c->request);
    }
}
