<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeAssetSummaryData;
use App\Application\Hr\Data\EmployeeLoanSummaryData;
use App\Application\Hr\Data\EmployeeOperationsWorkspaceData;
use App\Application\Hr\Data\ExpenseClaimSummaryData;
use App\Application\Hr\Data\HrHelpdeskSummaryData;
use App\Domain\Hr\Services\EmployeeOperationsRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEmployeeOperationsWorkspace
{
    public function __construct(private readonly EmployeeOperationsRegister $register) {}

    public function execute(
        User $actor,
        string $active,
        ?LengthAwarePaginator $assets = null,
        ?LengthAwarePaginator $claims = null,
        ?LengthAwarePaginator $loans = null,
        ?LengthAwarePaginator $tickets = null,
        array $filters = [],
    ): EmployeeOperationsWorkspaceData {
        if ($active === 'assets') {
            $assets ??= $this->register->assets($actor, $filters, 'page');
            $assets->setPath(route('hr.assets.index'));

            return $this->data(
                actor: $actor,
                active: $active,
                assets: $this->register->presentAssets($actor, $assets),
                claims: $this->emptyPaginator(route('hr.expense-claims.index')),
                loans: $this->emptyPaginator(route('hr.loans.index')),
                tickets: $this->emptyPaginator(route('hr.helpdesk-tickets.index')),
                assetSummary: $this->register->assetSummary($actor, $filters),
                includeCompanies: true,
            );
        }

        if ($active === 'claims') {
            $claims ??= $this->register->claims($actor, $filters, 'page');
            $claims->setPath(route('hr.expense-claims.index'));

            return $this->data(
                actor: $actor,
                active: $active,
                assets: $this->emptyPaginator(route('hr.assets.index')),
                claims: $this->register->presentClaims($actor, $claims),
                loans: $this->emptyPaginator(route('hr.loans.index')),
                tickets: $this->emptyPaginator(route('hr.helpdesk-tickets.index')),
                claimSummary: $this->register->claimSummary($actor, $filters),
            );
        }

        if ($active === 'loans') {
            $loans ??= $this->register->loans($actor, $filters, 'page');
            $loans->setPath(route('hr.loans.index'));

            return $this->data(
                actor: $actor,
                active: $active,
                assets: $this->emptyPaginator(route('hr.assets.index')),
                claims: $this->emptyPaginator(route('hr.expense-claims.index')),
                loans: $this->register->presentLoans($actor, $loans),
                tickets: $this->emptyPaginator(route('hr.helpdesk-tickets.index')),
                loanSummary: $this->register->loanSummary($actor, $filters),
            );
        }

        if ($active === 'helpdesk') {
            $tickets ??= $this->register->tickets($actor, $filters, 'page');
            $tickets->setPath(route('hr.helpdesk-tickets.index'));

            return $this->data(
                actor: $actor,
                active: $active,
                assets: $this->emptyPaginator(route('hr.assets.index')),
                claims: $this->emptyPaginator(route('hr.expense-claims.index')),
                loans: $this->emptyPaginator(route('hr.loans.index')),
                tickets: $this->register->presentHelpdesk($actor, $tickets),
                helpdeskSummary: $this->register->helpdeskSummary($actor, $filters),
                includeAssignees: true,
            );
        }

        throw new \InvalidArgumentException('Unsupported employee operations register.');
    }

    private function data(
        User $actor,
        string $active,
        LengthAwarePaginator $assets,
        LengthAwarePaginator $claims,
        LengthAwarePaginator $loans,
        LengthAwarePaginator $tickets,
        ?ExpenseClaimSummaryData $claimSummary = null,
        ?EmployeeLoanSummaryData $loanSummary = null,
        ?EmployeeAssetSummaryData $assetSummary = null,
        ?HrHelpdeskSummaryData $helpdeskSummary = null,
        bool $includeAssignees = false,
        bool $includeCompanies = false,
    ): EmployeeOperationsWorkspaceData {
        return new EmployeeOperationsWorkspaceData(
            $active, $assets, $claims, $loans, $tickets,
            $this->register->employees($actor, $active),
            $includeAssignees ? $this->register->assignees($actor) : collect(),
            $includeCompanies ? $this->register->companies($actor) : collect(),
            ['Laptop', 'Mobile', 'Tablet', 'Access Card', 'Vehicle', 'Tool', 'Other'], ['new', 'good', 'fair', 'damaged'], ['available', 'assigned', 'recovered', 'retired', 'lost'],
            [['value' => 'travel', 'label' => 'Travel'], ['value' => 'food', 'label' => 'Food'], ['value' => 'fuel', 'label' => 'Fuel'], ['value' => 'mobile', 'label' => 'Mobile'], ['value' => 'medical', 'label' => 'Medical'], ['value' => 'office', 'label' => 'Office'], ['value' => 'other', 'label' => 'Other']], ['submitted', 'approved', 'rejected', 'paid'],
            [['value' => 'salary_advance', 'label' => 'Salary Advance'], ['value' => 'emergency', 'label' => 'Emergency'], ['value' => 'welfare', 'label' => 'Welfare'], ['value' => 'other', 'label' => 'Other']], ['submitted', 'approved', 'rejected', 'disbursed', 'closed'],
            ['payroll', 'attendance', 'leave', 'documents', 'assets', 'policy', 'other'], ['low', 'medium', 'high', 'critical'], ['open', 'assigned', 'resolved', 'closed'], $this->register->abilities($actor),
            $claimSummary,
            $loanSummary,
            $assetSummary,
            $helpdeskSummary,
        );
    }

    private function emptyPaginator(string $path): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 15, 1, ['path' => $path]);
    }
}
