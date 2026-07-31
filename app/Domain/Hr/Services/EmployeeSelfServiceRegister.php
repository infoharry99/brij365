<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\EmployeeSelfServiceDashboardData;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Models\EmployeePolicyAcknowledgement;
use App\Models\ExpenseClaim;
use App\Models\HrHelpdeskTicket;
use App\Models\LeaveRequest;
use App\Models\PayrollRunItem;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Hr\EmployeePolicyAcknowledgementService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Support\Carbon;

final class EmployeeSelfServiceRegister
{
    private const PRESENT_STATUSES = ['present', 'late', 'early_leave', 'half_day', 'overtime'];

    public function __construct(
        private readonly EmployeeRegister $employees,
        private readonly CompanyScopeService $scope,
        private readonly EmployeePolicyAcknowledgementService $policies,
    ) {}

    public function read(User $actor): ?EmployeeSelfServiceDashboardData
    {
        if (! $actor->hasPermission('employee.self_service')) {
            return null;
        }

        $employee = $this->employees->self($actor);
        if (! $employee || ! $this->scope->allows($actor, $employee->company_id)) {
            return null;
        }

        $abilities = $this->abilities($actor);
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthAttendance = AttendanceRecord::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$monthStart, $monthEnd]);
        $attendanceMarked = (clone $monthAttendance)->count();
        $attendancePresent = (clone $monthAttendance)->whereIn('status', self::PRESENT_STATUSES)->count();

        $leaveBalances = $employee->leaveBalances()
            ->with('leaveType:id,code,name,requires_document,allows_half_day')
            ->where('period_year', now()->year)
            ->orderBy('leave_type_id')
            ->get();

        $latestPayslip = $abilities['canViewPayrollSummary']
            ? PayrollRunItem::query()
                ->with('payrollRun:id,period_year,period_month,status')
                ->where('payroll_run_items.company_id', $employee->company_id)
                ->where('payroll_run_items.employee_id', $employee->id)
                ->whereHas('payrollRun', fn ($query) => $query->where('status', 'approved'))
                ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_run_items.payroll_run_id')
                ->orderByDesc('payroll_runs.period_year')
                ->orderByDesc('payroll_runs.period_month')
                ->select('payroll_run_items.*')
                ->first()
            : null;

        $openRequests = $this->openRequestCount($employee, $abilities);

        return new EmployeeSelfServiceDashboardData(
            employee: $employee,
            summary: [
                'attendance_percent' => $attendanceMarked > 0 ? round(($attendancePresent / $attendanceMarked) * 100, 1) : null,
                'attendance_marked_days' => (int) $attendanceMarked,
                'attendance_present_days' => (int) $attendancePresent,
                'leave_available_days' => round((float) $leaveBalances->sum('available_days'), 2),
                'open_requests' => $openRequests,
                'latest_payslip_period' => $latestPayslip
                    ? Carbon::create((int) $latestPayslip->payrollRun->period_year, (int) $latestPayslip->payrollRun->period_month, 1)->format('m/Y')
                    : null,
                'latest_payslip_status' => $latestPayslip?->payrollRun?->status,
                'latest_payslip_net_payable' => $latestPayslip ? (float) $latestPayslip->net_payable : null,
            ],
            recentAttendance: $this->recentAttendance($employee),
            myActions: $this->myActions($employee, $abilities),
            quickActions: $this->quickActions($employee, $abilities),
            leaveBalances: $leaveBalances->map(fn ($balance): array => [
                'id' => $balance->id,
                'code' => $balance->leaveType?->code ?? 'LEAVE',
                'name' => $balance->leaveType?->name ?? 'Leave',
                'available_days' => (float) $balance->available_days,
                'pending_days' => (float) $balance->pending_days,
                'requires_document' => (bool) ($balance->leaveType?->requires_document ?? false),
                'allows_half_day' => (bool) ($balance->leaveType?->allows_half_day ?? false),
            ])->values()->all(),
            abilities: $abilities,
        );
    }

    /** @return array<string, bool> */
    private function abilities(User $actor): array
    {
        $selfService = $actor->hasPermission('employee.self_service');

        return [
            'canCreateClaim' => $selfService || $actor->hasPermission('claims.create') || $actor->hasPermission('claims.manage'),
            'canViewClaims' => $selfService || $actor->hasPermission('claims.view') || $actor->hasPermission('claims.manage'),
            'canCreateLeaveRequest' => $selfService || $actor->hasPermission('leave.request') || $actor->hasPermission('leave.manage'),
            'canViewLeaveRequests' => $selfService || $actor->hasPermission('leave.view') || $actor->hasPermission('leave.request') || $actor->hasPermission('leave.manage'),
            'canCreateAttendanceRegularization' => $selfService || $actor->hasPermission('attendance.request') || $actor->hasPermission('attendance.manage'),
            'canViewAttendanceRegularizations' => $selfService || $actor->hasPermission('attendance.view') || $actor->hasPermission('attendance.request') || $actor->hasPermission('attendance.manage'),
            'canViewPerformanceReviews' => $selfService || $actor->hasPermission('performance.view') || $actor->hasPermission('performance.manage'),
            'canSubmitSelfReview' => $selfService || $actor->hasPermission('performance.self_review'),
            'canCreatePolicyAcknowledgement' => $selfService || $actor->hasPermission('hr.manage'),
            'canViewPolicyAcknowledgements' => $selfService || $actor->hasPermission('hr.view') || $actor->hasPermission('hr.manage'),
            'canViewPayrollSummary' => $selfService || $actor->hasPermission('payroll.view') || $actor->hasPermission('payroll.manage'),
            'canCreateHelpdeskTicket' => $selfService || $actor->hasPermission('helpdesk.create') || $actor->hasPermission('helpdesk.manage'),
        ];
    }

    /** @param array<string, bool> $abilities */
    private function openRequestCount(Employee $employee, array $abilities): int
    {
        $count = 0;
        if ($abilities['canViewLeaveRequests']) {
            $count += $employee->leaveRequests()->where('status', 'submitted')->count();
        }
        if ($abilities['canViewAttendanceRegularizations']) {
            $count += $employee->attendanceRegularizationRequests()->where('status', 'submitted')->count();
        }
        if ($abilities['canViewClaims']) {
            $count += $employee->expenseClaims()->whereIn('status', ['submitted', 'approved'])->count();
        }
        if ($abilities['canCreateHelpdeskTicket']) {
            $count += $employee->hrHelpdeskTickets()->whereNotIn('status', ['resolved', 'closed'])->count();
        }

        return $count;
    }

    /** @return array<int, array<string, mixed>> */
    private function recentAttendance(Employee $employee): array
    {
        $start = now()->startOfDay()->subDays(13);

        return AttendanceRecord::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', now()->toDateString())
            ->orderByDesc('work_date')
            ->get()
            ->map(function (AttendanceRecord $record): array {
                $status = (string) $record->status;

                return [
                    'id' => $record->id,
                    'work_date' => $record->work_date->toDateString(),
                    'day_label' => $record->work_date->format('D, d M'),
                    'status' => $status,
                    'status_label' => str($status)->replace('_', ' ')->title()->toString(),
                    'tone' => match ($status) {
                        'present', 'overtime' => 'success',
                        'late', 'early_leave', 'half_day' => 'warning',
                        'absent' => 'danger',
                        'on_leave', 'weekly_off', 'holiday' => 'info',
                        default => 'neutral',
                    },
                    'late_minutes' => (int) $record->late_minutes,
                    'early_leave_minutes' => (int) $record->early_leave_minutes,
                    'worked_minutes' => (int) $record->worked_minutes,
                ];
            })->values()->all();
    }

    /** @param array<string, bool> $abilities @return array<int, array<string, mixed>> */
    private function myActions(Employee $employee, array $abilities): array
    {
        $actions = collect();

        if ($abilities['canViewLeaveRequests']) {
            $employee->leaveRequests()->where('status', 'submitted')->latest()->limit(4)->get()
                ->each(fn (LeaveRequest $request) => $actions->push($this->actionRow(
                    'leave-'.$request->id,
                    'leave',
                    'Leave request awaiting a decision',
                    ($request->starts_on?->format('d M') ?? 'Start date').' to '.($request->ends_on?->format('d M Y') ?? 'end date'),
                    $request->status,
                    route('hr.leave-requests.index', ['status' => 'submitted']),
                    'info',
                    $request->created_at,
                )));
        }

        if ($abilities['canViewAttendanceRegularizations']) {
            $employee->attendanceRegularizationRequests()->where('status', 'submitted')->latest()->limit(4)->get()
                ->each(fn (AttendanceRegularizationRequest $request) => $actions->push($this->actionRow(
                    'attendance-'.$request->id,
                    'attendance',
                    'Attendance correction awaiting a decision',
                    $request->work_date?->format('d M Y') ?? 'Work date unavailable',
                    $request->status,
                    route('hr.attendance-regularizations.index', ['status' => 'submitted']),
                    'warning',
                    $request->created_at,
                )));
        }

        if ($abilities['canViewClaims']) {
            $employee->expenseClaims()->whereIn('status', ['submitted', 'approved'])->latest()->limit(4)->get()
                ->each(fn (ExpenseClaim $claim) => $actions->push($this->actionRow(
                    'claim-'.$claim->id,
                    'claim',
                    'Expense claim '.$claim->claim_number,
                    str($claim->claim_type)->replace('_', ' ')->title()->toString(),
                    $claim->status,
                    route('hr.expense-claims.index', ['status' => $claim->status]),
                    'info',
                    $claim->created_at,
                )));
        }

        if ($abilities['canCreateHelpdeskTicket']) {
            $employee->hrHelpdeskTickets()->whereNotIn('status', ['resolved', 'closed'])->latest()->limit(4)->get()
                ->each(fn (HrHelpdeskTicket $ticket) => $actions->push($this->actionRow(
                    'helpdesk-'.$ticket->id,
                    'helpdesk',
                    $ticket->subject,
                    'HR request '.$ticket->ticket_number,
                    $ticket->status,
                    route('hr.helpdesk-tickets.index', ['status' => $ticket->status]),
                    $ticket->priority === 'urgent' ? 'danger' : 'info',
                    $ticket->created_at,
                )));
        }

        if ($abilities['canSubmitSelfReview']) {
            $employee->performanceReviews()->where('status', 'draft')->latest()->limit(3)->get()
                ->each(fn (PerformanceReview $review) => $actions->push($this->actionRow(
                    'review-'.$review->id,
                    'performance',
                    'Complete your self review',
                    $review->review_number,
                    $review->status,
                    route('hr.performance-reviews.index', ['status' => 'draft']),
                    'warning',
                    $review->created_at,
                )));
        }

        if ($abilities['canCreatePolicyAcknowledgement']) {
            $catalogue = collect($this->policies->policyCatalogue($employee));
            $acknowledged = EmployeePolicyAcknowledgement::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->where('status', 'acknowledged')
                ->whereIn('policy_key', $catalogue->pluck('policy_key')->filter()->values())
                ->get(['policy_key', 'policy_version'])
                ->mapWithKeys(fn (EmployeePolicyAcknowledgement $row): array => [
                    $row->policy_key.'@'.(int) $row->policy_version => true,
                ]);

            foreach ($catalogue as $policy) {
                $isAcknowledged = $acknowledged->has(
                    $policy['policy_key'].'@'.(int) $policy['policy_version'],
                );
                if (! $isAcknowledged) {
                    $actions->push($this->actionRow(
                        'policy-'.$policy['policy_key'],
                        'policy',
                        'Acknowledge '.$policy['policy_title'],
                        (string) $policy['summary'],
                        'pending',
                        route('hr.policy-acknowledgements.index'),
                        'warning',
                        null,
                    ));
                }
            }
        }

        return $actions->sortByDesc(fn (array $row): string => $row['occurred_at'] ?? '9999-12-31T23:59:59Z')->take(12)->values()->all();
    }

    /** @param array<string, bool> $abilities @return array<int, array<string, mixed>> */
    private function quickActions(Employee $employee, array $abilities): array
    {
        return collect([
            $abilities['canCreateLeaveRequest'] ? [
                'key' => 'leave', 'label' => 'Apply for leave', 'description' => 'Submit a leave request for approval.',
                'url' => route('hr.leave-requests.index'), 'icon' => 'calendar', 'tone' => 'primary',
            ] : null,
            $abilities['canCreateAttendanceRegularization'] ? [
                'key' => 'attendance', 'label' => 'Correct attendance', 'description' => 'Request a correction for a missing or incorrect attendance day.',
                'url' => route('hr.attendance-regularizations.index'), 'icon' => 'clock', 'tone' => 'info',
            ] : null,
            $abilities['canCreateClaim'] ? [
                'key' => 'claim', 'label' => 'New expense claim', 'description' => 'Submit an eligible business expense.',
                'url' => route('hr.expense-claims.index'), 'icon' => 'receipt', 'tone' => 'success',
            ] : null,
            $abilities['canCreateHelpdeskTicket'] ? [
                'key' => 'helpdesk', 'label' => 'Ask HR', 'description' => 'Open a private HR service request.',
                'url' => route('hr.helpdesk-tickets.index'), 'icon' => 'headset', 'tone' => 'warning',
            ] : null,
            [
                'key' => 'documents', 'label' => 'My documents', 'description' => 'Review your authorized employee documents.',
                'url' => route('hr.employees.documents.index', $employee), 'icon' => 'folder-open', 'tone' => 'info',
            ],
            $abilities['canViewPayrollSummary'] ? [
                'key' => 'payroll', 'label' => 'Payroll summary', 'description' => 'Review approved payroll periods and totals available to you.',
                'url' => route('hr.employees.payroll-summary.show', $employee), 'icon' => 'file-invoice-dollar', 'tone' => 'purple',
            ] : null,
        ])->filter()->values()->all();
    }

    private function actionRow(string $id, string $type, string $title, string $description, string $status, string $url, string $tone, $occurredAt): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'url' => $url,
            'tone' => $tone,
            'occurred_at' => $occurredAt?->toISOString(),
        ];
    }
}
