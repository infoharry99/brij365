<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\HrCommandCenterData;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeSeparationSettlement;
use App\Models\HrHelpdeskTicket;
use App\Models\JobOpening;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PerformanceReview;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HrCommandCenterRegister
{
    private const ACTIVE_EMPLOYEE_STATUSES = ['active', 'probation', 'on_notice'];

    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly EmployeeFieldVisibility $fieldVisibility,
    ) {}

    public function read(User $actor): HrCommandCenterData
    {
        if (! $this->hasAny($actor, ['hr.view', 'hr.manage'])) {
            throw new AuthorizationException('You do not have access to the HR Command Center.');
        }

        $abilities = $this->abilities($actor);
        $employees = $this->scoped(Employee::query(), $actor);
        $activeHeadcount = (clone $employees)->whereIn('status', self::ACTIVE_EMPLOYEE_STATUSES)->count();
        $totalHeadcount = (clone $employees)->count();

        $attendanceMarked = null;
        $attendancePresent = null;
        $attendancePercent = null;
        if ($abilities['canViewAttendance']) {
            $attendance = $this->scoped(AttendanceRecord::query(), $actor)
                ->whereDate('work_date', now()->toDateString());
            $attendanceMarked = (clone $attendance)->count();
            $attendancePresent = (clone $attendance)
                ->whereIn('status', ['present', 'late', 'early_leave', 'half_day', 'overtime'])
                ->count();
            $attendancePercent = $activeHeadcount > 0
                ? round(($attendancePresent / $activeHeadcount) * 100, 1)
                : null;
        }

        $latestPayroll = null;
        if ($abilities['canViewPayroll']) {
            $latestPayroll = $this->scoped(PayrollRun::query(), $actor)
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->first();
        }

        $pending = $this->pendingCounts($actor, $abilities);
        $requiredSettingKeys = array_values(array_filter(
            (array) config('builder360.system_settings.required_active_keys', []),
            fn ($key): bool => is_string($key) && $this->isHrSettingKey($key),
        ));
        $missingSettings = $abilities['canViewCompliance']
            ? $this->missingActiveSettingKeys($actor, $requiredSettingKeys)
            : [];

        $openPositions = $abilities['canViewRecruitment']
            ? (int) $this->scoped(JobOpening::query(), $actor)
                ->whereIn('status', ['open', 'pending_approval'])
                ->sum('positions')
            : null;

        return new HrCommandCenterData(
            summary: [
                'active_headcount' => (int) $activeHeadcount,
                'total_headcount' => (int) $totalHeadcount,
                'attendance_today_percent' => $attendancePercent,
                'attendance_present_today' => $attendancePresent,
                'attendance_marked_today' => $attendanceMarked,
                'pending_approvals' => array_sum($pending),
                'pending_leave_requests' => $pending['leave'],
                'pending_attendance_regularizations' => $pending['attendance'],
                'pending_confirmations' => $pending['confirmations'],
                'pending_settlements' => $pending['settlements'],
                'pending_payroll_runs' => $pending['payroll'],
                'pending_performance_reviews' => $pending['performance'],
                'open_positions' => $openPositions,
                'compliance_alerts' => $abilities['canViewCompliance'] ? count($missingSettings) : null,
                'latest_payroll_net_payable' => $latestPayroll ? (float) $latestPayroll->net_payable : null,
                'latest_payroll_label' => $abilities['canViewPayroll']
                    ? ($latestPayroll ? sprintf('%02d/%d · %s', $latestPayroll->period_month, $latestPayroll->period_year, $latestPayroll->status) : 'No payroll run')
                    : 'Restricted',
            ],
            approvalInbox: $this->approvalInbox($actor, $abilities),
            departmentHeadcount: $this->departmentHeadcount($actor, $activeHeadcount),
            lifecycleDue: $this->lifecycleDue($actor, $abilities),
            complianceRisk: $this->complianceRisk($actor, $abilities, $requiredSettingKeys, $missingSettings),
            abilities: $abilities,
        );
    }

    /** @return array<string, bool> */
    private function abilities(User $actor): array
    {
        return [
            'canViewAttendance' => $this->hasAny($actor, ['attendance.view', 'attendance.manage', 'attendance.approve', 'hr.manage']),
            'canViewPayroll' => $this->fieldVisibility->canViewCompensation($actor),
            'canViewRecruitment' => $this->hasAny($actor, ['recruitment.view', 'recruitment.manage', 'recruitment.approve']),
            'canViewCompliance' => $this->hasAny($actor, ['compliance.view', 'compliance.manage', 'hr.manage']),
            'canViewLeave' => $this->hasAny($actor, ['leave.view', 'leave.manage', 'leave.approve', 'hr.manage']),
            'canViewConfirmations' => $this->hasAny($actor, ['hr.view', 'hr.manage', 'performance.manage']),
            'canViewSettlements' => $this->hasAny($actor, ['hr.view', 'hr.manage', 'finance.view', 'finance.approve']),
            'canViewPerformance' => $this->hasAny($actor, ['performance.view', 'performance.manage', 'performance.approve', 'hr.manage']),
            'canViewHelpdesk' => $this->hasAny($actor, ['helpdesk.view', 'helpdesk.manage', 'hr.manage']),
        ];
    }

    /** @param array<string, bool> $abilities @return array<string, int> */
    private function pendingCounts(User $actor, array $abilities): array
    {
        return [
            'leave' => $abilities['canViewLeave']
                ? $this->scoped(LeaveRequest::query(), $actor)->where('status', 'submitted')->count()
                : 0,
            'attendance' => $abilities['canViewAttendance']
                ? $this->scoped(AttendanceRegularizationRequest::query(), $actor)->where('status', 'submitted')->count()
                : 0,
            'confirmations' => $abilities['canViewConfirmations']
                ? $this->scoped(EmployeeConfirmationCase::query(), $actor)->whereIn('status', ['due', 'manager_recommended'])->count()
                : 0,
            'settlements' => $abilities['canViewSettlements']
                ? $this->scoped(EmployeeSeparationSettlement::query(), $actor)->whereIn('status', ['initiated', 'hr_approved', 'finance_approved'])->count()
                : 0,
            'payroll' => $abilities['canViewPayroll']
                ? $this->scoped(PayrollRun::query(), $actor)->whereIn('status', ['draft', 'generated'])->count()
                : 0,
            'performance' => $abilities['canViewPerformance']
                ? $this->scoped(PerformanceReview::query(), $actor)->whereIn('status', ['draft', 'self_submitted', 'manager_submitted'])->count()
                : 0,
        ];
    }

    /** @param array<string, bool> $abilities @return array<int, array<string, mixed>> */
    private function approvalInbox(User $actor, array $abilities): array
    {
        $rows = collect();

        if ($abilities['canViewLeave']) {
            $this->scoped(LeaveRequest::query(), $actor)
                ->with(['employee:id,employee_code,name,department', 'leaveType:id,code,name'])
                ->where('status', 'submitted')->latest()->limit(8)->get()
                ->each(fn (LeaveRequest $request) => $rows->push($this->approvalRow(
                    id: 'leave-'.$request->id,
                    type: 'Leave',
                    reference: $request->request_number,
                    subject: ($request->employee?->name ?? 'Employee').' · '.($request->leaveType?->name ?? 'Leave').' · '.$request->requested_days.' day(s)',
                    owner: $request->employee?->department ?? 'HR',
                    status: $request->status,
                    createdAt: $request->created_at,
                    url: route('hr.leave-requests.index', ['status' => 'submitted']),
                    canApprove: $actor->can('approve', $request),
                )));
        }

        if ($abilities['canViewAttendance']) {
            $this->scoped(AttendanceRegularizationRequest::query(), $actor)
                ->with('employee:id,employee_code,name,department')
                ->where('status', 'submitted')->latest()->limit(8)->get()
                ->each(fn (AttendanceRegularizationRequest $request) => $rows->push($this->approvalRow(
                    id: 'attendance-'.$request->id,
                    type: 'Attendance',
                    reference: $request->request_number,
                    subject: ($request->employee?->name ?? 'Employee').' · regularization for '.($request->work_date?->toDateString() ?? 'date unavailable'),
                    owner: $request->employee?->department ?? 'HR',
                    status: $request->status,
                    createdAt: $request->created_at,
                    url: route('hr.attendance-regularizations.index', ['status' => 'submitted']),
                    canApprove: $actor->can('approve', $request),
                )));
        }

        if ($abilities['canViewConfirmations']) {
            $this->scoped(EmployeeConfirmationCase::query(), $actor)
                ->with(['employee:id,employee_code,name,department', 'managerEmployee:id,name'])
                ->whereIn('status', ['due', 'manager_recommended'])->orderBy('review_due_on')->limit(8)->get()
                ->each(fn (EmployeeConfirmationCase $case) => $rows->push($this->approvalRow(
                    id: 'confirmation-'.$case->id,
                    type: 'Confirmation',
                    reference: $case->case_number,
                    subject: ($case->employee?->name ?? 'Employee').' · probation review',
                    owner: $case->managerEmployee?->name ?? 'HR',
                    status: $case->status,
                    createdAt: $case->created_at,
                    url: route('hr.confirmation-cases.index', ['status' => $case->status]),
                    canApprove: $actor->can('recommend', $case) || $actor->can('decide', $case),
                )));
        }

        if ($abilities['canViewPayroll']) {
            $this->scoped(PayrollRun::query(), $actor)
                ->whereIn('status', ['draft', 'generated'])->latest()->limit(5)->get()
                ->each(fn (PayrollRun $run) => $rows->push($this->approvalRow(
                    id: 'payroll-'.$run->id,
                    type: 'Payroll',
                    reference: $run->run_number,
                    subject: sprintf('Payroll period %02d/%d', $run->period_month, $run->period_year),
                    owner: 'Payroll / Finance',
                    status: $run->status,
                    createdAt: $run->created_at,
                    url: route('payroll.runs.index', ['status' => $run->status]),
                    canApprove: $actor->can('approve', $run),
                )));
        }

        if ($abilities['canViewSettlements']) {
            $this->scoped(EmployeeSeparationSettlement::query(), $actor)
                ->with('employee:id,employee_code,name,department')
                ->whereIn('status', ['initiated', 'hr_approved', 'finance_approved'])->latest()->limit(6)->get()
                ->each(fn (EmployeeSeparationSettlement $settlement) => $rows->push($this->approvalRow(
                    id: 'settlement-'.$settlement->id,
                    type: 'F&F',
                    reference: $settlement->settlement_number,
                    subject: ($settlement->employee?->name ?? 'Employee').' · settlement review',
                    owner: 'HR / Finance',
                    status: $settlement->status,
                    createdAt: $settlement->created_at,
                    url: route('hr.separation-settlements.index', ['status' => $settlement->status]),
                    canApprove: $actor->can('hrApprove', $settlement)
                        || $actor->can('financeApprove', $settlement)
                        || $actor->can('complete', $settlement),
                )));
        }

        return $rows->sortByDesc('created_at')->take(25)->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function departmentHeadcount(User $actor, int $activeHeadcount): array
    {
        return $this->scoped(Employee::query(), $actor)
            ->whereIn('status', self::ACTIVE_EMPLOYEE_STATUSES)
            ->select('department', DB::raw('count(*) as aggregate'))
            ->groupBy('department')->orderByDesc('aggregate')->limit(12)->get()
            ->map(fn ($row): array => [
                'department' => $row->department ?: 'Unassigned',
                'employees' => (int) $row->aggregate,
                'percentage' => $activeHeadcount > 0 ? round(((int) $row->aggregate / $activeHeadcount) * 100, 1) : 0.0,
            ])->values()->all();
    }

    /** @param array<string, bool> $abilities @return array<int, array<string, mixed>> */
    private function lifecycleDue(User $actor, array $abilities): array
    {
        $rows = collect();
        if ($abilities['canViewConfirmations']) {
            $this->scoped(EmployeeConfirmationCase::query(), $actor)
                ->with(['employee:id,employee_code,name,department', 'managerEmployee:id,name'])
                ->whereIn('status', ['due', 'manager_recommended'])->orderBy('review_due_on')->limit(8)->get()
                ->each(fn (EmployeeConfirmationCase $case) => $rows->push([
                    'id' => 'confirmation-'.$case->id,
                    'employee' => $case->employee?->name ?? 'Employee',
                    'employee_code' => $case->employee?->employee_code,
                    'event' => 'Probation / Confirmation',
                    'due' => $case->review_due_on?->toDateString(),
                    'due_label' => $case->review_due_on?->format('d M Y') ?? 'Not scheduled',
                    'owner' => $case->managerEmployee?->name ?? 'HR',
                    'status' => $case->status,
                    'url' => route('hr.confirmation-cases.index', ['status' => $case->status]),
                ]));
        }
        if ($abilities['canViewSettlements']) {
            $this->scoped(EmployeeSeparationSettlement::query(), $actor)
                ->with('employee:id,employee_code,name,department')
                ->whereIn('status', ['initiated', 'hr_approved', 'finance_approved'])->orderBy('last_working_date')->limit(8)->get()
                ->each(fn (EmployeeSeparationSettlement $settlement) => $rows->push([
                    'id' => 'settlement-'.$settlement->id,
                    'employee' => $settlement->employee?->name ?? 'Employee',
                    'employee_code' => $settlement->employee?->employee_code,
                    'event' => 'Separation / F&F',
                    'due' => $settlement->last_working_date?->toDateString(),
                    'due_label' => $settlement->last_working_date?->format('d M Y') ?? 'Not scheduled',
                    'owner' => 'HR / Finance',
                    'status' => $settlement->status,
                    'url' => route('hr.separation-settlements.index', ['status' => $settlement->status]),
                ]));
        }

        return $rows->sortBy(fn (array $row): string => $row['due'] ?? '9999-12-31')->take(16)->values()->all();
    }

    /** @param array<string, bool> $abilities @param array<int, string> $requiredKeys @param array<int, string> $missingKeys */
    private function complianceRisk(User $actor, array $abilities, array $requiredKeys, array $missingKeys): array
    {
        if (! $abilities['canViewCompliance']) {
            return [];
        }

        $companyId = $this->scope->companyIdFor($actor);
        $active = SystemSetting::query()->whereIn('setting_key', $requiredKeys)->where('status', 'active')
            ->where(function (Builder $query) use ($companyId): void {
                $query->whereNull('company_id');
                if ($companyId !== null && $companyId > 0) {
                    $query->orWhere('company_id', $companyId);
                }
            })->orderBy('setting_key')->limit(20)->get();

        return collect($missingKeys)->map(fn (string $key): array => [
            'key' => $key,
            'name' => str($key)->replace(['.', '_'], ' ')->title()->toString(),
            'version' => 'missing',
            'effective' => null,
            'verification' => 'Missing active setting',
            'company' => 'Required',
            'tone' => 'danger',
            'url' => route('hr.compliance-rule-settings.index'),
        ])->merge($active->map(fn (SystemSetting $setting): array => [
            'key' => $setting->setting_key,
            'name' => $setting->label,
            'version' => 'v'.$setting->version,
            'effective' => $setting->effective_from?->toDateString() ?? 'Immediate',
            'verification' => $setting->status,
            'company' => $setting->company_id ? 'Company' : 'Global',
            'tone' => 'success',
            'url' => route('hr.compliance-rule-settings.index'),
        ]))->values()->all();
    }

    /** @param array<int, string> $requiredKeys @return array<int, string> */
    private function missingActiveSettingKeys(User $actor, array $requiredKeys): array
    {
        if ($requiredKeys === []) {
            return [];
        }

        $companyId = $this->scope->companyIdFor($actor);
        $active = SystemSetting::query()->whereIn('setting_key', $requiredKeys)->where('status', 'active')
            ->where(function (Builder $query) use ($companyId): void {
                $query->whereNull('company_id');
                if ($companyId !== null && $companyId > 0) {
                    $query->orWhere('company_id', $companyId);
                }
            })->pluck('setting_key')->unique()->all();

        return array_values(array_diff($requiredKeys, $active));
    }

    private function approvalRow(string $id, string $type, ?string $reference, string $subject, string $owner, string $status, $createdAt, string $url, bool $canApprove): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'reference' => $reference,
            'subject' => $subject,
            'owner' => $owner,
            'status' => $status,
            'age' => $createdAt?->diffForHumans(short: true) ?? 'new',
            'created_at' => $createdAt?->toISOString(),
            'url' => $url,
            'can_approve' => $canApprove,
        ];
    }

    private function scoped(Builder $query, User $actor): Builder
    {
        return $this->scope->apply($query, $actor);
    }

    private function isHrSettingKey(string $key): bool
    {
        return collect(['hr.', 'payroll.', 'attendance.', 'leave.', 'recruitment.', 'performance.'])
            ->contains(fn (string $prefix): bool => str_starts_with($key, $prefix));
    }

    /** @param array<int, string> $permissions */
    private function hasAny(User $actor, array $permissions): bool
    {
        return $actor->hasPermission('*') || collect($permissions)->contains(fn (string $permission): bool => $actor->hasPermission($permission));
    }
}
