<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceRegularizationRequest;
use App\Services\Hr\AttendanceService;

final class RejectAttendanceRegularization
{
    public function __construct(private readonly AttendanceService $s) {}

    public function execute(AttendanceRegularizationRequest $r, HrCommandData $c): AttendanceRegularizationRequest
    {
        return $this->s->rejectRegularization($r, $c->attributes, $c->actor, $c->request);
    }
}
