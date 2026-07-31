<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceShift;
use App\Services\Hr\AttendanceService;

final class CreateAttendanceShift
{
    public function __construct(private readonly AttendanceService $s) {}

    public function execute(HrCommandData $c): AttendanceShift
    {
        return $this->s->createShift($c->attributes, $c->actor, $c->request);
    }
}
