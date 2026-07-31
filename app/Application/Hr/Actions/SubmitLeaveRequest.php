<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\LeaveRequest;
use App\Services\Hr\LeaveRequestService;

final class SubmitLeaveRequest
{
    public function __construct(private readonly LeaveRequestService $s) {}

    public function execute(HrCommandData $c): LeaveRequest
    {
        return $this->s->submit($c->attributes, $c->actor, $c->request);
    }
}
