<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\LeaveEncashment;
use App\Services\Hr\LeaveProcessingService;

final class ApproveLeaveEncashment
{
    public function __construct(private readonly LeaveProcessingService $s) {}

    public function execute(LeaveEncashment $e, HrCommandData $c): LeaveEncashment
    {
        return $this->s->approveEncashment($e, $c->attributes, $c->actor, $c->request);
    }
}
