<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceRegularizationRequest;
use App\Services\Hr\AttendanceService;

final class SubmitAttendanceRegularization
{
    public function __construct(private readonly AttendanceService $s) {}

    public function execute(HrCommandData $c): AttendanceRegularizationRequest
    {
        return $this->s->submitRegularization($c->attributes, $c->actor, $c->request);
    }
}
