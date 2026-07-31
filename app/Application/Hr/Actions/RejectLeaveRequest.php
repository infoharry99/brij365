<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\LeaveRequest;
use App\Services\Hr\LeaveRequestService;

final class RejectLeaveRequest
{
    public function __construct(private readonly LeaveRequestService $s) {}

    public function execute(LeaveRequest $r, HrCommandData $c): LeaveRequest
    {
        return $this->s->reject($r, $c->attributes, $c->actor, $c->request);
    }
}
