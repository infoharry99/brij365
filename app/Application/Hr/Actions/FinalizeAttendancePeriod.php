<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendancePeriodLock;
use App\Services\Hr\AttendanceRosterManager;

final class FinalizeAttendancePeriod
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(HrCommandData $command): AttendancePeriodLock
    {
        return $this->manager->finalizePeriod($command->attributes, $command->actor, $command->request);
    }
}
