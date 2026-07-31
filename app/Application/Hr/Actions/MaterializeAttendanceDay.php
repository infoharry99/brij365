<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\AttendanceDailyMaterializer;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Carbon;

final class MaterializeAttendanceDay
{
    public function __construct(private readonly AttendanceDailyMaterializer $materializer) {}

    public function execute(Employee $employee, Carbon|string $workDate, ?User $actor = null): AttendanceRecord
    {
        return $this->materializer->materialize($employee, $workDate, $actor);
    }
}
