<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceRoster;
use App\Services\Hr\AttendanceRosterManager;

final class CreateAttendanceRoster
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(HrCommandData $command): AttendanceRoster
    {
        return $this->manager->createRoster($command->attributes, $command->actor, $command->request);
    }
}
