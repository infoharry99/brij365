<?php

namespace App\Domain\Hr\Services;

use DateTimeZone;
use Illuminate\Validation\ValidationException;

final class AttendanceRosterRulePackValidator
{
    public const ATTENDANCE_KEY = 'hr.attendance.rules';

    public const ROSTER_KEY = 'hr.attendance.roster_rules';

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    public function normalize(string $settingKey, array $value): array
    {
        return match ($settingKey) {
            self::ATTENDANCE_KEY => $this->attendance($value),
            self::ROSTER_KEY => $this->roster($value),
            default => throw ValidationException::withMessages([
                'setting_key' => 'The selected setting is not an attendance or roster rule pack.',
            ]),
        };
    }

    /** @param array<string, mixed> $value */
    private function attendance(array $value): array
    {
        $timezone = $this->timezone($value['company_timezone'] ?? $value['timezone'] ?? null, true);
        $lateGrace = $this->nullableInteger($value, 'late_grace_minutes', 0, 1440);
        $earlyGrace = $this->nullableInteger($value, 'early_leave_grace_minutes', 0, 1440);
        $halfDay = $this->nullableInteger($value, 'half_day_threshold_minutes', 0, 2880);
        $fullDay = $this->nullableInteger($value, 'full_day_threshold_minutes', 0, 2880);
        if ($halfDay !== null && $fullDay !== null && $fullDay < $halfDay) {
            throw ValidationException::withMessages([
                'value.full_day_threshold_minutes' => 'The full-day threshold must be greater than or equal to the half-day threshold.',
            ]);
        }

        $rounding = (string) ($value['rounding'] ?? 'nearest_minute');
        if (! in_array($rounding, ['nearest_minute', 'floor_minute', 'ceil_minute'], true)) {
            throw ValidationException::withMessages([
                'value.rounding' => 'Attendance rounding must be nearest_minute, floor_minute, or ceil_minute.',
            ]);
        }

        return [
            'company_timezone' => $timezone,
            'late_grace_minutes' => $lateGrace,
            'early_leave_grace_minutes' => $earlyGrace,
            'half_day_threshold_minutes' => $halfDay,
            'full_day_threshold_minutes' => $fullDay,
            'rounding' => $rounding,
        ];
    }

    /** @param array<string, mixed> $value */
    private function roster(array $value): array
    {
        $scope = (string) ($value['coverage_scope'] ?? 'roster_employees');
        if (! in_array($scope, ['roster_employees', 'all_active_employees'], true)) {
            throw ValidationException::withMessages([
                'value.coverage_scope' => 'Roster coverage scope must be roster_employees or all_active_employees.',
            ]);
        }

        return [
            'company_timezone' => $this->timezone($value['company_timezone'] ?? $value['timezone'] ?? 'Asia/Kolkata'),
            'block_shift_overlaps' => $this->boolean($value, 'block_shift_overlaps', true),
            'minimum_rest_minutes' => $this->integer($value, 'minimum_rest_minutes', 0, 2880, 0),
            'maximum_consecutive_workdays' => $this->integer($value, 'maximum_consecutive_workdays', 0, 31, 0),
            'require_complete_period_assignment' => $this->boolean($value, 'require_complete_period_assignment', false),
            'coverage_scope' => $scope,
            'publication_lead_days' => $this->integer($value, 'publication_lead_days', 0, 90, 0),
            'swap_request_cutoff_hours' => $this->integer($value, 'swap_request_cutoff_hours', 0, 720, 0),
            'maximum_rotation_generation_horizon_days' => $this->integer($value, 'maximum_rotation_generation_horizon_days', 1, 366, 366),
            'roster_reopen_limit_days' => $this->integer($value, 'roster_reopen_limit_days', 0, 3650, 0),
            'attendance_reopen_limit_days' => $this->integer($value, 'attendance_reopen_limit_days', 0, 3650, 0),
        ];
    }

    private function timezone(mixed $value, bool $nullable = false): ?string
    {
        if (($value === null || $value === '') && $nullable) {
            return null;
        }

        $timezone = trim((string) $value);
        if ($timezone === '' || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages([
                'value.company_timezone' => 'Select a valid IANA timezone for attendance and roster calculations.',
            ]);
        }

        return $timezone;
    }

    /** @param array<string, mixed> $value */
    private function nullableInteger(array $value, string $key, int $min, int $max): ?int
    {
        if (! array_key_exists($key, $value) || $value[$key] === null || $value[$key] === '') {
            return null;
        }

        return $this->integer($value, $key, $min, $max, $min);
    }

    /** @param array<string, mixed> $value */
    private function integer(array $value, string $key, int $min, int $max, int $default): int
    {
        $candidate = $value[$key] ?? $default;
        if (filter_var($candidate, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(["value.{$key}" => str($key)->headline().' must be a whole number.']);
        }

        $integer = (int) $candidate;
        if ($integer < $min || $integer > $max) {
            throw ValidationException::withMessages(["value.{$key}" => str($key)->headline()." must be between {$min} and {$max}."]);
        }

        return $integer;
    }

    /** @param array<string, mixed> $value */
    private function boolean(array $value, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $value)) {
            return $default;
        }

        $candidate = filter_var($value[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($candidate === null) {
            throw ValidationException::withMessages(["value.{$key}" => str($key)->headline().' must be true or false.']);
        }

        return $candidate;
    }
}
