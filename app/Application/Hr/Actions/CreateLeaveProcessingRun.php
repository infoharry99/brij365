<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\LeaveProcessingRun;
use App\Services\Hr\LeaveProcessingService;

final class CreateLeaveProcessingRun
{
    public function __construct(private readonly LeaveProcessingService $s) {}

    public function execute(HrCommandData $c): LeaveProcessingRun
    {
        return $this->s->createProcessingRun($c->attributes, $c->actor, $c->request);
    }
}
