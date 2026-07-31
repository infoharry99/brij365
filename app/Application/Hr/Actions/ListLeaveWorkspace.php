<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\LeaveWorkspaceData;
use App\Domain\Hr\Services\LeaveWorkspaceRegister;
use App\Models\LeaveEncashment;
use App\Models\LeaveProcessingRun;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ListLeaveWorkspace
{
    public function __construct(private readonly LeaveWorkspaceRegister $r) {}

    public function execute(User $u, string $active, ?LengthAwarePaginator $types = null, ?LengthAwarePaginator $balances = null, ?LengthAwarePaginator $requests = null, ?LengthAwarePaginator $runs = null, ?LengthAwarePaginator $encashments = null): LeaveWorkspaceData
    {
        $types = $active === 'types'
            ? $this->r->presentPolicies($types ?? $this->r->types($u))
            : null;
        $balances = $active === 'balances'
            ? $this->r->presentBalances($balances ?? $this->r->balances($u))
            : null;
        $requests = $active === 'requests'
            ? $this->r->presentRequests($u, $requests ?? $this->r->requests($u))
            : null;
        $runs = $active === 'processing'
            ? $this->r->presentProcessingRuns($u, $runs ?? $this->r->processingRuns($u))
            : null;
        $encashments = $active === 'encashments'
            ? $this->r->presentEncashments($u, $encashments ?? $this->r->encashments($u))
            : null;

        $types?->setPath(route('hr.leave-types.index'));
        $balances?->setPath(route('hr.leave-balances.index'));
        $requests?->setPath(route('hr.leave-requests.index'));
        $runs?->setPath(route('hr.leave-processing-runs.index'));
        $encashments?->setPath(route('hr.leave-encashments.index'));

        $needsEmployees = in_array($active, ['requests', 'balances', 'encashments'], true);
        $needsLeaveTypes = in_array($active, ['requests', 'encashments'], true);

        return new LeaveWorkspaceData(
            activeRegister: $active,
            summary: $this->r->summary($u),
            types: $types,
            balances: $balances,
            leaveRequests: $requests,
            processingRuns: $runs,
            encashments: $encashments,
            companies: $active === 'processing' ? $this->r->companies($u) : new Collection,
            employees: $needsEmployees ? $this->r->employees($u) : new Collection,
            leaveTypeOptions: $needsLeaveTypes ? $this->r->leaveTypeOptions($u) : new Collection,
            requestStatuses: [['value' => 'submitted', 'label' => 'Submitted'], ['value' => 'approved', 'label' => 'Approved'], ['value' => 'rejected', 'label' => 'Rejected'], ['value' => 'cancelled', 'label' => 'Cancelled']],
            processingStatuses: [['value' => 'preview', 'label' => 'Preview'], ['value' => 'posted', 'label' => 'Posted'], ['value' => 'cancelled', 'label' => 'Cancelled']],
            processingTypes: [['value' => 'monthly_accrual', 'label' => 'Monthly accrual'], ['value' => 'year_end', 'label' => 'Year end']],
            encashmentStatuses: [['value' => 'submitted', 'label' => 'Submitted'], ['value' => 'approved', 'label' => 'Approved'], ['value' => 'rejected', 'label' => 'Rejected'], ['value' => 'payroll_marked', 'label' => 'Payroll marked']],
            abilities: [
                'canCreateLeaveRequest' => $u->can('create', LeaveRequest::class),
                'canApproveLeaveRequest' => $u->hasPermission('leave.approve'),
                'canCreateProcessingRun' => $u->can('create', LeaveProcessingRun::class),
                'canPostProcessingRun' => $u->hasPermission('leave.approve'),
                'canCreateEncashment' => $u->can('create', LeaveEncashment::class),
                'canApproveEncashment' => $u->hasPermission('leave.approve'),
                'canMarkEncashmentPayroll' => $u->hasPermission('payroll.manage'),
                'canManageLeave' => $u->hasPermission('leave.manage'),
            ],
        );
    }
}
