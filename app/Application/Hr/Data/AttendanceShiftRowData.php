<?php

namespace App\Application\Hr\Data;

final readonly class AttendanceShiftRowData
{
    /**
     * @param array<int, string> $rules
     * @param array<int, array{sequence: int, label: ?string, timing: string}> $segments
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $timing,
        public bool $overnight,
        public int $lateGraceMinutes,
        public int $earlyLeaveGraceMinutes,
        public int $halfDayThresholdMinutes,
        public int $fullDayThresholdMinutes,
        public array $rules,
        public array $segments,
        public int $activeAssignments,
    ) {}
}
