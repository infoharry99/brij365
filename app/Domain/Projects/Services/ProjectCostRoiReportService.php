<?php

namespace App\Domain\Projects\Services;

use App\Application\Projects\Data\ProjectCostRoiExportData;
use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Models\Project;
use App\Models\ScoreSnapshot;
use App\Models\User;
use App\Services\Governance\ManagementReportService;
use App\Services\Governance\ReportLimitPolicy;
use App\Services\Security\CompanyScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ProjectCostRoiReportService
{
    private const HEADER = 'project_code,project_name,company_code,company_name,branch_code,city,state,status,project_type,total_units,sold_units,available_units,booked_units,registered_units,handed_over_units,budget_amount,approved_contractor_spend,approved_purchase_order_spend,total_approved_spend,remaining_budget,budget_used_percent,booked_revenue,approved_collections,outstanding_amount,collection_percent,average_construction_progress_percent,target_roi_percent,revenue_to_spend_roi_percent,projected_profit,health_score,starts_on,ends_on';

    public function __construct(
        private CompanyScopeService $companyScope,
        private ManagementReportService $reports,
        private ReportLimitPolicy $reportLimitPolicy,
        private ActiveScoringRuleResolver $activeRules,
    ) {}

    /** @param array<string, mixed> $filters */
    public function build(User $actor, array $filters): ProjectCostRoiExportData
    {
        $projects = $this->query($actor, $filters)
            ->orderBy('code')
            ->limit($this->maximumRows())
            ->get();
        $scores = $this->currentProjectHealthScores($projects);
        $rows = $projects->map(fn (Project $project): array => $this->row(
            $project,
            $scores->get($project->id),
        ))->all();

        return new ProjectCostRoiExportData(
            content: $rows === [] ? self::HEADER."\n" : $this->reports->csv($rows),
            rowCount: count($rows),
            filename: 'builder360-project-cost-roi.csv',
        );
    }

    public function maximumRows(): int
    {
        return $this->reportLimitPolicy->maxExportRows();
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<Project>
     */
    private function query(User $actor, array $filters): Builder
    {
        return $this->companyScope->apply(
            Project::query()->with([
                'company:id,code,name',
                'branch:id,code,name',
                'units:id,project_id,status',
                'bookings:id,project_id,status,net_receivable',
                'collectionReceipts:id,project_id,status,amount',
                'contractorMeasurements:id,project_id,status,certified_total',
                'purchaseOrders:id,project_id,status,total_amount',
                'constructionMilestones:id,project_id,progress_percent',
            ]),
            $actor,
        )
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->whereKey($filters['project_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']));
    }

    /**
     * @param Collection<int, Project> $projects
     * @return Collection<int, string>
     */
    private function currentProjectHealthScores(Collection $projects): Collection
    {
        $companyId = (int) ($projects->first()?->company_id ?? 0);
        $rule = $companyId > 0 ? $this->activeRules->resolve($companyId, 'project_health') : null;

        if ($rule === null || $projects->isEmpty()) {
            return collect();
        }

        return ScoreSnapshot::query()
            ->where('company_id', $companyId)
            ->where('scoring_rule_id', $rule->id)
            ->where('subject_type', Project::class)
            ->whereIn('subject_id', $projects->modelKeys())
            ->where('is_current', true)
            ->get(['subject_id', 'total_score'])
            ->mapWithKeys(fn (ScoreSnapshot $snapshot): array => [
                (int) $snapshot->subject_id => number_format((float) $snapshot->total_score, 2, '.', ''),
            ]);
    }

    /** @return array<string, mixed> */
    private function row(Project $project, ?string $healthScore): array
    {
        $units = $project->units;
        $bookings = $project->bookings->whereIn('status', ['confirmed', 'agreement_pending', 'registered']);
        $collections = $project->collectionReceipts->where('status', 'approved');
        $contractorSpend = (float) $project->contractorMeasurements->where('status', 'approved')->sum('certified_total');
        $purchaseOrderSpend = (float) $project->purchaseOrders
            ->whereIn('status', ['approved', 'partially_received', 'received'])
            ->sum('total_amount');
        $totalSpend = round($contractorSpend + $purchaseOrderSpend, 2);
        $budget = (float) $project->budget_amount;
        $revenue = (float) $bookings->sum('net_receivable');
        $collected = (float) $collections->sum('amount');
        $outstanding = max($revenue - $collected, 0);
        $progress = (float) ($project->constructionMilestones->avg('progress_percent') ?? 0);
        $soldUnits = $units->whereIn('status', ['booked', 'registered', 'handed_over'])->count();
        $collectionPercent = $revenue > 0 ? ($collected / $revenue) * 100 : 0;
        $budgetUsedPercent = $budget > 0 ? ($totalSpend / $budget) * 100 : 0;

        return [
            'project_code' => $project->code,
            'project_name' => $project->name,
            'company_code' => $project->company?->code,
            'company_name' => $project->company?->name,
            'branch_code' => $project->branch?->code,
            'city' => $project->city,
            'state' => $project->state,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'total_units' => $units->count(),
            'sold_units' => $soldUnits,
            'available_units' => $units->where('status', 'available')->count(),
            'booked_units' => $units->where('status', 'booked')->count(),
            'registered_units' => $units->where('status', 'registered')->count(),
            'handed_over_units' => $units->where('status', 'handed_over')->count(),
            'budget_amount' => round($budget, 2),
            'approved_contractor_spend' => round($contractorSpend, 2),
            'approved_purchase_order_spend' => round($purchaseOrderSpend, 2),
            'total_approved_spend' => $totalSpend,
            'remaining_budget' => round(max($budget - $totalSpend, 0), 2),
            'budget_used_percent' => round($budgetUsedPercent, 2),
            'booked_revenue' => round($revenue, 2),
            'approved_collections' => round($collected, 2),
            'outstanding_amount' => round($outstanding, 2),
            'collection_percent' => round($collectionPercent, 2),
            'average_construction_progress_percent' => round($progress, 2),
            'target_roi_percent' => (float) $project->target_roi_percent,
            'revenue_to_spend_roi_percent' => $totalSpend > 0 ? round(($revenue / $totalSpend) * 100, 2) : null,
            'projected_profit' => round($revenue - $totalSpend, 2),
            'health_score' => $healthScore,
            'starts_on' => $project->starts_on?->toDateString(),
            'ends_on' => $project->ends_on?->toDateString(),
        ];
    }
}
