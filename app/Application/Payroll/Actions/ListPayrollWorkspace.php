<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollWorkspaceData;
use App\Domain\Payroll\Services\PayrollWorkspaceRegister;
use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPayrollWorkspace
{
    public function __construct(private readonly PayrollWorkspaceRegister $register) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $actor, string $activeRegister, array $filters = [], ?LengthAwarePaginator $components = null, ?LengthAwarePaginator $structures = null, ?LengthAwarePaginator $runs = null, ?LengthAwarePaginator $batches = null, ?LengthAwarePaginator $commissionRules = null, ?LengthAwarePaginator $commissionRuns = null): PayrollWorkspaceData
    {
        $components = $activeRegister === 'components'
            ? $this->register->presentComponents($components ?? $this->register->components($actor, $filters))
            : null;
        $structures = $activeRegister === 'structures'
            ? $this->register->presentStructures($structures ?? $this->register->structures($actor, $filters))
            : null;
        $runs = $activeRegister === 'runs'
            ? $this->register->presentRuns($actor, $runs ?? $this->register->runs($actor, $filters))
            : null;
        $batches = $activeRegister === 'bank_batches'
            ? $this->register->presentBankBatches($actor, $batches ?? $this->register->bankBatches($actor, $filters), (bool) ($filters['include_payload'] ?? false))
            : null;
        $commissionRules = $activeRegister === 'commission_rules'
            ? $this->register->presentCommissionRules($commissionRules ?? $this->register->commissionRules($actor, $filters))
            : null;
        $commissionRuns = $activeRegister === 'commission_runs'
            ? $this->register->presentCommissionRuns($actor, $commissionRuns ?? $this->register->commissionRuns($actor, $filters))
            : null;

        $components?->setPath(route('payroll.components.index'));
        $structures?->setPath(route('payroll.salary-structures.index'));
        $runs?->setPath(route('payroll.runs.index'));
        $batches?->setPath(route('payroll.bank-transfer-batches.index'));
        $commissionRules?->setPath(route('payroll.commission-rules.index'));
        $commissionRuns?->setPath(route('payroll.commission-runs.index'));

        return new PayrollWorkspaceData(
            $activeRegister, $this->register->summary($actor), $components, $structures, $runs, $batches, $commissionRules, $commissionRuns,
            [['value' => 'earning', 'label' => 'Earning'], ['value' => 'deduction', 'label' => 'Deduction']],
            [['value' => 'generated', 'label' => 'Generated'], ['value' => 'approved', 'label' => 'Approved']],
            [['value' => 'prepared', 'label' => 'Prepared'], ['value' => 'released', 'label' => 'Released']],
            [['value' => 'fixed', 'label' => 'Fixed'], ['value' => 'percentage', 'label' => 'Percentage'], ['value' => 'slab', 'label' => 'Slab'], ['value' => 'target', 'label' => 'Target']],
            [['value' => 'booking_value', 'label' => 'Booking value'], ['value' => 'collection_received', 'label' => 'Collection received']],
            [['value' => 'draft', 'label' => 'Draft'], ['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive']],
            [['value' => 'generated', 'label' => 'Generated'], ['value' => 'approved', 'label' => 'Approved'], ['value' => 'rejected', 'label' => 'Rejected']],
            $this->register->commissionRuleOptions($actor),
            $this->register->projectOptions($actor),
            [
                'canGenerateRun' => $actor->can('create', PayrollRun::class),
                'canApproveRun' => $actor->hasPermission('payroll.approve'),
                'canPrepareBatch' => $actor->hasPermission('payroll.manage'),
                'canReleaseBatch' => $actor->hasPermission('payroll.approve'),
                'canViewBankPayload' => $actor->can('viewPayload', PayrollBankTransferBatch::class),
                'canManagePayroll' => $actor->hasPermission('payroll.manage'),
                'canCreateCommissionRule' => $actor->can('create', \App\Models\CommissionRule::class),
                'canGenerateCommissionRun' => $actor->can('create', \App\Models\CommissionRun::class),
            ],
        );
    }
}
