<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\LeaveEncashment;
use App\Services\Hr\LeaveProcessingService;

final class SubmitLeaveEncashment
{
    public function __construct(private readonly LeaveProcessingService $s) {}

    public function execute(HrCommandData $c): LeaveEncashment
    {
        return $this->s->submitEncashment($c->attributes, $c->actor, $c->request);
    }
}
