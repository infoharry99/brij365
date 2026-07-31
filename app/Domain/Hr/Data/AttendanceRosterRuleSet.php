<?php

namespace App\Domain\Hr\Data;

final readonly class AttendanceRosterRuleSet
{
    /**
     * @param  array<string, mixed>  $ruleContext
     */
    public function __construct(
        public string $timezone,
        public ?int $lateGraceMinutes,
        public ?int $earlyLeaveGraceMinutes,
        public ?int $halfDayThresholdMinutes,
        public ?int $fullDayThresholdMinutes,
        public string $rounding,
        public bool $blockShiftOverlaps,
        public int $minimumRestMinutes,
        public int $maximumConsecutiveWorkdays,
        public bool $requireCompletePeriodAssignment,
        public string $coverageScope,
        public int $publicationLeadDays,
        public int $swapRequestCutoffHours,
        public int $maximumRotationGenerationHorizonDays,
        public int $rosterReopenLimitDays,
        public int $attendanceReopenLimitDays,
        public array $ruleContext,
    ) {}

    /** @return array<string, mixed> */
    public function effectiveAttendanceValues(): array
    {
        return [
            'timezone' => $this->timezone,
            'late_grace_minutes' => $this->lateGraceMinutes,
            'early_leave_grace_minutes' => $this->earlyLeaveGraceMinutes,
            'half_day_threshold_minutes' => $this->halfDayThresholdMinutes,
            'full_day_threshold_minutes' => $this->fullDayThresholdMinutes,
            'rounding' => $this->rounding,
        ];
    }

    /** @return array<string, mixed> */
    public function effectiveRosterValues(): array
    {
        return [
            'timezone' => $this->timezone,
            'block_shift_overlaps' => $this->blockShiftOverlaps,
            'minimum_rest_minutes' => $this->minimumRestMinutes,
            'maximum_consecutive_workdays' => $this->maximumConsecutiveWorkdays,
            'require_complete_period_assignment' => $this->requireCompletePeriodAssignment,
            'coverage_scope' => $this->coverageScope,
            'publication_lead_days' => $this->publicationLeadDays,
            'swap_request_cutoff_hours' => $this->swapRequestCutoffHours,
            'maximum_rotation_generation_horizon_days' => $this->maximumRotationGenerationHorizonDays,
            'roster_reopen_limit_days' => $this->rosterReopenLimitDays,
            'attendance_reopen_limit_days' => $this->attendanceReopenLimitDays,
        ];
    }
}
