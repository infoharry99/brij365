<?php

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Data\AttendanceRosterRuleSet;
use App\Domain\Payroll\Services\CanonicalPayrollHasher;
use App\Models\SystemSetting;
use App\Services\Settings\SystemSettingResolver;
use Illuminate\Support\Carbon;

final class AttendanceRosterRulePackResolver
{
    public function __construct(
        private readonly SystemSettingResolver $settings,
        private readonly AttendanceRosterRulePackValidator $validator,
        private readonly CanonicalPayrollHasher $hasher,
    ) {}

    public function resolve(int $companyId, Carbon|string|null $effectiveOn = null): AttendanceRosterRuleSet
    {
        $on = $effectiveOn instanceof Carbon
            ? $effectiveOn
            : ($effectiveOn !== null ? Carbon::parse($effectiveOn) : now());
        $attendanceSetting = $this->settings->active($companyId, AttendanceRosterRulePackValidator::ATTENDANCE_KEY, $on);
        $rosterSetting = $this->settings->active($companyId, AttendanceRosterRulePackValidator::ROSTER_KEY, $on);

        $attendance = $attendanceSetting
            ? $this->validator->normalize(AttendanceRosterRulePackValidator::ATTENDANCE_KEY, (array) $attendanceSetting->value)
            : $this->validator->normalize(AttendanceRosterRulePackValidator::ATTENDANCE_KEY, []);
        $roster = $rosterSetting
            ? $this->validator->normalize(AttendanceRosterRulePackValidator::ROSTER_KEY, (array) $rosterSetting->value)
            : $this->validator->normalize(AttendanceRosterRulePackValidator::ROSTER_KEY, []);
        $timezone = (string) ($rosterSetting !== null
            ? $roster['company_timezone']
            : ($attendance['company_timezone'] ?? 'Asia/Kolkata'));

        return new AttendanceRosterRuleSet(
            timezone: $timezone,
            lateGraceMinutes: $attendance['late_grace_minutes'],
            earlyLeaveGraceMinutes: $attendance['early_leave_grace_minutes'],
            halfDayThresholdMinutes: $attendance['half_day_threshold_minutes'],
            fullDayThresholdMinutes: $attendance['full_day_threshold_minutes'],
            rounding: (string) $attendance['rounding'],
            blockShiftOverlaps: (bool) $roster['block_shift_overlaps'],
            minimumRestMinutes: (int) $roster['minimum_rest_minutes'],
            maximumConsecutiveWorkdays: (int) $roster['maximum_consecutive_workdays'],
            requireCompletePeriodAssignment: (bool) $roster['require_complete_period_assignment'],
            coverageScope: (string) $roster['coverage_scope'],
            publicationLeadDays: (int) $roster['publication_lead_days'],
            swapRequestCutoffHours: (int) $roster['swap_request_cutoff_hours'],
            maximumRotationGenerationHorizonDays: (int) $roster['maximum_rotation_generation_horizon_days'],
            rosterReopenLimitDays: (int) $roster['roster_reopen_limit_days'],
            attendanceReopenLimitDays: (int) $roster['attendance_reopen_limit_days'],
            ruleContext: [
                'resolved_for' => $on->toDateString(),
                'attendance' => $this->context($attendanceSetting, AttendanceRosterRulePackValidator::ATTENDANCE_KEY, $attendance),
                'roster' => $this->context($rosterSetting, AttendanceRosterRulePackValidator::ROSTER_KEY, $roster),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function context(?SystemSetting $setting, string $key, array $normalized): array
    {
        return [
            'setting_key' => $key,
            'setting_id' => $setting?->id,
            'version' => (int) ($setting?->version ?? 0),
            'checksum' => $this->hasher->hash($normalized),
            'effective_from' => $setting?->effective_from?->toDateString(),
            'effective_to' => $setting?->effective_to?->toDateString(),
            'source' => $setting ? 'active_system_setting' : 'safe_default',
        ];
    }
}
