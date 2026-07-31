<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Domain\Hr\Services\AttendanceSourceEventRecorder;
use App\Models\AttendanceSourceEvent;

final class AppendAttendanceSourceEvent
{
    public function __construct(private readonly AttendanceSourceEventRecorder $recorder) {}

    public function execute(HrCommandData $command): AttendanceSourceEvent
    {
        return $this->recorder->append($command->attributes, $command->actor, $command->request);
    }
}
