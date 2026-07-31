<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionProgressWorkspaceData;
use App\Domain\Construction\Services\ConstructionWorkspaceRegister;
use App\Models\ConstructionMilestone;
use App\Models\DailyProgressReport;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListConstructionProgressWorkspace
{
    public function __construct(private readonly ConstructionWorkspaceRegister $register) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters, string $activeRegister, ?LengthAwarePaginator $milestones = null, ?LengthAwarePaginator $reports = null): ConstructionProgressWorkspaceData
    {
        return new ConstructionProgressWorkspaceData(
            activeRegister: $activeRegister,
            filters: $filters,
            milestones: ($milestones ?? $this->register->milestones($user, [], 'milestones_page'))->withQueryString(),
            dailyReports: ($reports ?? $this->register->dailyReports($user, [], 'reports_page'))->withQueryString(),
            projects: $this->register->projects($user),
            milestoneOptions: $this->register->milestoneOptions($user),
            phases: $this->register->phases($user),
            milestoneStatuses: ['planned' => 'Planned', 'in_progress' => 'In progress', 'completed' => 'Completed', 'delayed' => 'Delayed'],
            dailyReportStatuses: ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'],
            milestoneMetrics: $this->register->milestoneMetrics($user),
            dailyReportMetrics: $this->register->reportMetrics($user),
            canCreateMilestone: $user->can('create', ConstructionMilestone::class),
            canCreateDailyReport: $user->can('create', DailyProgressReport::class),
        );
    }
}
