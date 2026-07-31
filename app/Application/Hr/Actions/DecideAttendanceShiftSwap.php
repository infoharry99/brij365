<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceShiftSwapRequest;
use App\Services\Hr\AttendanceRosterManager;
use InvalidArgumentException;

final class DecideAttendanceShiftSwap
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(AttendanceShiftSwapRequest $swap, string $decision, HrCommandData $command): AttendanceShiftSwapRequest
    {
        return match ($decision) {
            'approved' => $this->manager->approveSwap($swap, (int) $command->attributes['lock_version'], $command->actor, $command->attributes['decision_note'] ?? null, $command->request),
            'rejected' => $this->manager->rejectSwap($swap, (int) $command->attributes['lock_version'], $command->actor, (string) $command->attributes['decision_note'], $command->request),
            default => throw new InvalidArgumentException('Unsupported shift swap decision.'),
        };
    }
}
