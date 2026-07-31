<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceRotationRule;
use App\Services\Hr\AttendanceRosterManager;

final class CreateAttendanceRotationRule
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(HrCommandData $command): AttendanceRotationRule
    {
        return $this->manager->createRotation($command->attributes, $command->actor, $command->request);
    }
}
