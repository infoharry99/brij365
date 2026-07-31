<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendancePeriodLock;
use App\Services\Hr\AttendanceRosterManager;

final class ReopenAttendancePeriod
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(AttendancePeriodLock $periodLock, HrCommandData $command): AttendancePeriodLock
    {
        return $this->manager->reopenPeriod(
            $periodLock,
            (int) $command->attributes['lock_version'],
            $command->actor,
            (string) $command->attributes['reopen_reason'],
            $command->request,
        );
    }
}
