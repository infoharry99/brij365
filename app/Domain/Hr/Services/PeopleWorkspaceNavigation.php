<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\PeopleWorkspaceLinkData;
use App\Models\AttendanceRegularizationRequest;
use App\Models\AttendanceRoster;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSeparationSettlement;
use App\Models\ExpenseClaim;
use App\Models\HrHelpdeskTicket;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PerformanceReview;
use App\Models\SystemSetting;
use App\Models\User;

final class PeopleWorkspaceNavigation
{
    public function __construct(private readonly HrReportCatalog $reportCatalog) {}

    /**
     * Return only destinations the actor is authorized to open through the
     * corresponding route Form Request or policy.
     *
     * @return array<int, PeopleWorkspaceLinkData>
     */
    public function links(?User $actor): array
    {
        if (! $actor) {
            return [];
        }

        $links = [
            [$this->hasAny($actor, ['hr.view', 'hr.manage']), new PeopleWorkspaceLinkData('dashboard', 'HR Command Center', 'fa-table-columns', 'hr.dashboard')],
            [$actor->can('viewAny', Employee::class), new PeopleWorkspaceLinkData('employees', 'Employee Master', 'fa-address-card', 'hr.employees.index')],
            [$actor->can('viewAny', AttendanceRegularizationRequest::class), new PeopleWorkspaceLinkData('attendance', 'Attendance', 'fa-calendar-check', 'hr.attendance-records.index')],
            [$actor->can('viewAny', AttendanceRoster::class), new PeopleWorkspaceLinkData('shifts', 'Shifts & Rosters', 'fa-clock-rotate-left', 'hr.attendance-rosters.index')],
            [$actor->can('viewAny', LeaveRequest::class), new PeopleWorkspaceLinkData('leave', 'Leave Management', 'fa-plane-departure', 'hr.leave-requests.index')],
            [$actor->can('viewAny', PayrollRun::class), new PeopleWorkspaceLinkData('payroll', 'Payroll', 'fa-money-check-dollar', 'payroll.runs.index')],
            [$actor->can('viewAny', Candidate::class), new PeopleWorkspaceLinkData('recruitment', 'Recruitment', 'fa-user-plus', 'recruitment.candidates.index')],
            [$actor->can('viewAny', PerformanceReview::class), new PeopleWorkspaceLinkData('performance', 'Performance', 'fa-chart-line', 'hr.performance-dashboard.index')],
            [$this->canViewLifecycle($actor), new PeopleWorkspaceLinkData('lifecycle', 'Employee Lifecycle', 'fa-arrows-spin', 'hr.lifecycle.index')],
            [$actor->can('viewAny', Employee::class), new PeopleWorkspaceLinkData('documents', 'Documents', 'fa-folder-open', 'hr.employee-documents.index')],
            [$actor->can('viewAny', EmployeeAsset::class), new PeopleWorkspaceLinkData('assets', 'Asset Management', 'fa-laptop-file', 'hr.assets.index')],
            [$actor->can('viewAny', ExpenseClaim::class), new PeopleWorkspaceLinkData('claims', 'Claims', 'fa-receipt', 'hr.expense-claims.index')],
            [$actor->can('viewAny', EmployeeLoan::class), new PeopleWorkspaceLinkData('loans', 'Loans & Advances', 'fa-hand-holding-dollar', 'hr.loans.index')],
            [$actor->can('viewAny', HrHelpdeskTicket::class), new PeopleWorkspaceLinkData('helpdesk', 'HR Helpdesk', 'fa-headset', 'hr.helpdesk-tickets.index')],
            [$this->canViewCompliance($actor), new PeopleWorkspaceLinkData('compliance', 'Compliance Center', 'fa-shield-halved', 'hr.compliance-rule-settings.index')],
            [$this->reportCatalog->for($actor) !== [], new PeopleWorkspaceLinkData('reports', 'Reports & MIS', 'fa-chart-column', 'hr.reports.index')],
            [$actor->can('viewAny', SystemSetting::class), new PeopleWorkspaceLinkData('settings', 'HR Settings', 'fa-gears', 'hr.settings.index')],
        ];

        return collect($links)
            ->filter(static fn (array $link): bool => $link[0] === true)
            ->map(static fn (array $link): PeopleWorkspaceLinkData => $link[1])
            ->values()
            ->all();
    }

    private function canViewLifecycle(User $actor): bool
    {
        return $actor->can('viewAny', EmployeeConfirmationCase::class)
            || $actor->can('viewAny', EmployeeSeparationSettlement::class)
            || $actor->can('viewAny', EmployeeExitInterview::class);
    }

    private function canViewCompliance(User $actor): bool
    {
        return $actor->hasPermission('compliance.view')
            || $actor->hasPermission('compliance.manage')
            || $actor->can('viewAny', SystemSetting::class);
    }

    /** @param array<int, string> $permissions */
    private function hasAny(User $actor, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($actor->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
