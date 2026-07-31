<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceShiftSwapRequest;
use App\Services\Hr\AttendanceRosterManager;

final class SubmitAttendanceShiftSwap
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(HrCommandData $command): AttendanceShiftSwapRequest
    {
        return $this->manager->requestSwap($command->attributes, $command->actor, $command->request);
    }
}
