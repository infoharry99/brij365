<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeShiftAssignment;
use App\Services\Hr\AttendanceRosterManager;

final class CreateShiftAssignment
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(HrCommandData $command): EmployeeShiftAssignment
    {
        return $this->manager->assign($command->attributes, $command->actor, $command->request);
    }
}
