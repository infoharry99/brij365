<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceShiftSwapRequest;
use App\Services\Hr\AttendanceRosterManager;

final class CancelAttendanceShiftSwap
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(AttendanceShiftSwapRequest $swap, HrCommandData $command): AttendanceShiftSwapRequest
    {
        return $this->manager->cancelSwap(
            $swap,
            (int) $command->attributes['lock_version'],
            $command->actor,
            (string) ($command->attributes['cancellation_note'] ?? ''),
            $command->request,
        );
    }
}
