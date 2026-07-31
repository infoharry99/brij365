<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\AttendanceWorkspaceRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAttendanceRecords
{
    public function __construct(private readonly AttendanceWorkspaceRegister $r) {}

    public function execute(User $u, array $f): LengthAwarePaginator
    {
        return $this->r->records($u, $f);
    }
}
