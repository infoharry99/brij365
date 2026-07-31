<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\AttendanceRosterWorkspaceData;
use App\Domain\Hr\Services\AttendanceRosterRegister;
use App\Models\User;

final class ListAttendanceRosters
{
    public function __construct(private readonly AttendanceRosterRegister $register) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $actor, array $filters = []): AttendanceRosterWorkspaceData
    {
        return $this->register->workspace($actor, $filters);
    }
}
