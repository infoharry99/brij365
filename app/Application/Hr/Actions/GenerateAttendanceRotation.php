<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRotationRule;
use App\Services\Hr\AttendanceRosterManager;

final class GenerateAttendanceRotation
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(AttendanceRotationRule $rotation, AttendanceRoster $roster, HrCommandData $command): int
    {
        return $this->manager->generateRotation(
            $rotation,
            $roster,
            (int) $command->attributes['lock_version'],
            $command->actor,
            $command->attributes['start_date'] ?? null,
            $command->attributes['end_date'] ?? null,
            $command->request,
        );
    }
}
