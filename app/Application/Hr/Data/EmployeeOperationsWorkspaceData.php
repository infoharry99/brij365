<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class EmployeeOperationsWorkspaceData
{
    public function __construct(
        public string $activeRegister,
        public LengthAwarePaginator $assets,
        public LengthAwarePaginator $claims,
        public LengthAwarePaginator $loans,
        public LengthAwarePaginator $tickets,
        public Collection $employees,
        public Collection $assignees,
        public Collection $companies,
        public array $assetCategories,
        public array $assetConditions,
        public array $assetStatuses,
        public array $claimTypes,
        public array $claimStatuses,
        public array $loanTypes,
        public array $loanStatuses,
        public array $helpdeskCategories,
        public array $helpdeskPriorities,
        public array $helpdeskStatuses,
        public array $abilities,
        public ?ExpenseClaimSummaryData $claimSummary = null,
        public ?EmployeeLoanSummaryData $loanSummary = null,
        public ?EmployeeAssetSummaryData $assetSummary = null,
        public ?HrHelpdeskSummaryData $helpdeskSummary = null,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), $this->presentation());
    }

    private function presentation(): array
    {
        $presentation = match ($this->activeRegister) {
            'assets' => [
                'workspaceTitle' => 'Asset Management',
                'workspaceDescription' => 'Track company assets, assignments, condition, recovery, and accountable custody.',
            ],
            'claims' => [
                'workspaceTitle' => 'Expense Claims',
                'workspaceDescription' => 'Submit, review, approve, reject, and settle employee reimbursements in your authorized scope.',
            ],
            'loans' => [
                'workspaceTitle' => 'Loans & Advances',
                'workspaceDescription' => 'Manage governed employee loan and salary-advance requests through approval and disbursement.',
            ],
            'helpdesk' => [
                'workspaceTitle' => 'HR Helpdesk',
                'workspaceDescription' => 'Resolve employee support requests with assignment, priority, workflow history, and resolution evidence.',
            ],
            default => [
                'workspaceTitle' => 'Employee Operations',
                'workspaceDescription' => 'Governed employee operations within your authorized company scope.',
            ],
        };

        return [
            ...$presentation,
            'workspacePageTitle' => $presentation['workspaceTitle'].' - Builder360 ERP-CRM',
        ];
    }
}
