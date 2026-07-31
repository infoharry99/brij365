<?php

namespace App\Application\Hr\Data;

final readonly class AttendanceSummaryData
{
    public function __construct(
        public int $total,
        public int $present,
        public int $late,
        public int $earlyLeave,
        public int $halfDay,
        public int $absent,
        public float $attendanceRate,
        public int $pendingRegularizations,
    ) {}
}
