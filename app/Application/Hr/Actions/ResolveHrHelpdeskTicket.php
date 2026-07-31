<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\HrHelpdeskTicket;
use App\Services\Hr\EmployeeOperationsService;

final class ResolveHrHelpdeskTicket
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(HrHelpdeskTicket $ticket, HrCommandData $c): HrHelpdeskTicket
    {
        return $this->service->resolveHelpdeskTicket($ticket, $c->attributes, $c->actor, $c->request);
    }
}
