<?php

namespace App\Application\Hr\Data;

use App\Models\Employee;

final readonly class EmployeeDirectoryRowData
{
    public function __construct(
        public int $id,
        public string $employeeCode,
        public string $name,
        public string $initials,
        public ?int $userId,
        public bool $hasProfilePhoto,
        public string $designation,
        public string $department,
        public ?string $grade,
        public string $company,
        public string $branch,
        public string $project,
        public string $manager,
        public string $email,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public int $directReportsCount,
        public int $documentsCount,
        public string $attendanceLabel,
        public ?float $latestApprovedNetSalary,
    ) {}

    public static function fromEmployee(Employee $employee, bool $canViewCompensation): self
    {
        $attendanceDays = (int) ($employee->attendance_days_count ?? 0);
        $attendanceLabel = $attendanceDays > 0
            ? $attendanceDays.' recorded '.str('day')->plural($attendanceDays).' this month'
            : 'No records this month';

        $statusLabels = ['active' => 'Active', 'inactive' => 'Inactive', 'on_notice' => 'On notice', 'separated' => 'Separated'];
        $statusTones = ['active' => 'success', 'inactive' => 'muted', 'on_notice' => 'warning', 'separated' => 'danger'];

        return new self(
            id: (int) $employee->id,
            employeeCode: (string) $employee->employee_code,
            name: (string) $employee->name,
            initials: self::initials((string) $employee->name),
            userId: $employee->user_id ? (int) $employee->user_id : null,
            hasProfilePhoto: (bool) $employee->user?->profile_photo_path,
            designation: (string) $employee->designation,
            department: (string) $employee->department,
            grade: $employee->grade,
            company: $employee->company?->name ?? 'Company not available',
            branch: $employee->branch?->name ?? 'No branch',
            project: $employee->project?->name ?? 'All projects',
            manager: $employee->manager?->name ?? 'Not assigned',
            email: $employee->user?->email ?? 'No login linked',
            status: (string) $employee->status,
            statusLabel: $statusLabels[$employee->status] ?? ucfirst((string) $employee->status),
            statusTone: $statusTones[$employee->status] ?? 'muted',
            directReportsCount: (int) ($employee->direct_reports_count ?? 0),
            documentsCount: (int) ($employee->managed_documents_count ?? 0),
            attendanceLabel: $attendanceLabel,
            latestApprovedNetSalary: $canViewCompensation && $employee->latest_approved_net_salary !== null
                ? (float) $employee->latest_approved_net_salary
                : null,
        );
    }

    private static function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'E';
    }
}
