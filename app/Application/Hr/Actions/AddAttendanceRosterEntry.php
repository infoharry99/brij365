<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRosterEntry;
use App\Services\Hr\AttendanceRosterManager;

final class AddAttendanceRosterEntry
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(AttendanceRoster $roster, HrCommandData $command): AttendanceRosterEntry
    {
        return $this->manager->createEntry($roster, $command->attributes, $command->actor, $command->request);
    }
}
