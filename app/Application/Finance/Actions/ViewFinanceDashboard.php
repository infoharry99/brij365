<?php

namespace App\Application\Finance\Actions;

use App\Application\Finance\Data\FinanceDashboardPageData;
use App\Domain\Finance\Services\FinanceWorkspaceRegister;
use App\Models\User;
use App\Services\Finance\FinanceDashboardService;

final class ViewFinanceDashboard
{
    public function __construct(
        private readonly FinanceDashboardService $dashboard,
        private readonly FinanceWorkspaceRegister $register,
    ) {}

    public function execute(User $actor, array $filters): FinanceDashboardPageData
    {
        return new FinanceDashboardPageData(
            dashboard: $this->dashboard->dashboard($actor, $filters),
            filters: $filters,
            companies: $this->register->companies($actor),
            projects: $this->register->projects($actor),
        );
    }
}
