<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\DepartmentPerformanceRowData;
use App\Application\Hr\Data\PerformanceCycleRowData;
use App\Application\Hr\Data\PerformanceReviewRowData;
use App\Application\Hr\Data\PerformanceSummaryData;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Project;
use App\Models\User;
use App\Services\Hr\PerformanceManagementService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PerformanceRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
        private readonly PerformanceManagementService $performance,
    ) {}

    public function cycles(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->cycleQuery($actor)
            ->with(['company', 'project', 'createdBy', 'activatedBy'])
            ->withCount('reviews')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['frequency'] ?? null, fn ($query, string $frequency) => $query->where('frequency', $frequency))
            ->when($filters['department'] ?? null, fn ($query, string $department) => $query->where('department', $department))
            ->when($filters['project_id'] ?? null, fn ($query, int $projectId) => $query->where('project_id', $projectId))
            ->when($filters['current'] ?? false, fn ($query) => $query->whereDate('starts_on', '<=', now()->toDateString())->whereDate('ends_on', '>=', now()->toDateString()))
            ->orderByDesc('starts_on')
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
    }

    public function reviews(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->reviewQuery($actor)
            ->with($this->performance->reviewRelations())
            ->when($filters['cycle_id'] ?? null, fn ($query, int $cycleId) => $query->where('performance_cycle_id', $cycleId))
            ->when($filters['employee_id'] ?? null, fn ($query, int $employeeId) => $query->where('employee_id', $employeeId))
            ->when($filters['department'] ?? null, fn ($query, string $department) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('department', $department)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(array_key_exists('pip_required', $filters), fn ($query) => $query->where('pip_required', $filters['pip_required']))
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('period_end', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('period_start', '<=', $date))
            ->orderByDesc('period_end')
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
    }

    public function summary(User $actor, array $filters = [], string $activeRegister = 'dashboard'): PerformanceSummaryData
    {
        $cycleQuery = $this->cycleQuery($actor);
        $reviewQuery = $this->reviewQuery($actor);

        if ($activeRegister === 'dashboard') {
            $reviewQuery
                ->when($filters['cycle_id'] ?? null, fn (Builder $query, int $cycleId) => $query->where('performance_cycle_id', $cycleId))
                ->when($filters['department'] ?? null, fn (Builder $query, string $department) => $query->whereHas('employee', fn (Builder $employees) => $employees->where('department', $department)));
        }

        $average = (clone $reviewQuery)->whereNotNull('final_score')->avg('final_score');

        return new PerformanceSummaryData(
            cycles: (clone $cycleQuery)->count(),
            activeCycles: (clone $cycleQuery)->where('status', 'active')->count(),
            reviews: (clone $reviewQuery)->count(),
            openReviews: (clone $reviewQuery)->where('status', '!=', 'closed')->count(),
            closedReviews: (clone $reviewQuery)->where('status', 'closed')->count(),
            pipRequired: (clone $reviewQuery)->where('pip_required', true)->count(),
            averageFinalScore: $average === null ? null : number_format((float) $average, 2),
        );
    }

    public function departmentDashboard(User $actor, array $filters = []): Collection
    {
        return $this->reviewQuery($actor)
            ->join('employees as performance_employees', 'performance_employees.id', '=', 'performance_reviews.employee_id')
            ->when($filters['cycle_id'] ?? null, fn (Builder $query, int $cycleId) => $query->where('performance_reviews.performance_cycle_id', $cycleId))
            ->when($filters['department'] ?? null, fn (Builder $query, string $department) => $query->where('performance_employees.department', $department))
            ->selectRaw("COALESCE(performance_employees.department, 'Unassigned') as department")
            ->selectRaw('COUNT(DISTINCT performance_reviews.employee_id) as employee_count')
            ->selectRaw('COUNT(performance_reviews.id) as review_count')
            ->selectRaw("SUM(CASE WHEN performance_reviews.status = 'closed' THEN 1 ELSE 0 END) as closed_count")
            ->selectRaw("SUM(CASE WHEN performance_reviews.status <> 'closed' THEN 1 ELSE 0 END) as open_count")
            ->selectRaw('SUM(CASE WHEN performance_reviews.pip_required = 1 THEN 1 ELSE 0 END) as pip_count')
            ->selectRaw('AVG(performance_reviews.final_score) as average_final_score')
            ->groupBy('performance_employees.department')
            ->orderBy('department')
            ->get()
            ->map(function ($row): DepartmentPerformanceRowData {
                $reviews = (int) $row->review_count;
                $closed = (int) $row->closed_count;

                return new DepartmentPerformanceRowData(
                    department: (string) $row->department,
                    employees: (int) $row->employee_count,
                    reviews: $reviews,
                    openReviews: (int) $row->open_count,
                    closedReviews: $closed,
                    pipRequired: (int) $row->pip_count,
                    completionRate: $reviews === 0 ? '0.0' : number_format(($closed / $reviews) * 100, 1, '.', ''),
                    averageFinalScore: $row->average_final_score === null ? null : number_format((float) $row->average_final_score, 2),
                );
            });
    }

    public function presentCycles(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (PerformanceCycle $cycle) => new PerformanceCycleRowData(
            id: $cycle->id,
            code: $cycle->cycle_code,
            name: $cycle->name,
            frequency: Str::headline($cycle->frequency),
            period: ($cycle->starts_on?->format('d M Y') ?? 'Not available').' to '.($cycle->ends_on?->format('d M Y') ?? 'Not available'),
            reviewDue: $cycle->review_due_on?->format('d M Y') ?? 'Not set',
            department: $cycle->department ?: 'All departments',
            project: $cycle->project?->name ?? 'All projects',
            scale: $cycle->rating_scale_min.' to '.$cycle->rating_scale_max,
            passingScore: number_format((float) $cycle->passing_score, 2),
            status: $cycle->status,
            statusLabel: Str::headline($cycle->status),
            reviewCount: (int) $cycle->reviews_count,
        ));
    }

    public function presentReviews(User $actor, LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(function (PerformanceReview $review) use ($actor): PerformanceReviewRowData {
            $snapshot = $review->scoreSnapshot;
            $pendingOverride = $review->scoreOverrideRequests->firstWhere('status', 'pending');
            $canViewTrace = $actor->can('viewScoreTrace', $review);
            $canViewOverrideGovernance = $actor->can('viewOverrideGovernance', $review);
            $band = $snapshot?->scoringRule
                ? collect($snapshot->scoringRule->configuration['bands'] ?? [])->first(
                    static fn (array $candidate): bool => ($candidate['key'] ?? null) === $snapshot->score_band,
                )
                : null;

            return new PerformanceReviewRowData(
            id: $review->id,
            lockVersion: (int) $review->lock_version,
            number: $review->review_number,
            employeeCode: $review->employee?->employee_code ?? 'Not available',
            employeeName: $review->employee?->name ?? 'Unknown employee',
            department: $review->employee?->department ?: 'Not assigned',
            managerName: $review->managerEmployee?->name ?? 'Not assigned',
            cycleName: $review->cycle?->name ?? 'Unknown cycle',
            period: ($review->period_start?->format('d M Y') ?? 'Not available').' to '.($review->period_end?->format('d M Y') ?? 'Not available'),
            ratingScaleMin: (int) ($review->cycle?->rating_scale_min ?? 1),
            ratingScaleMax: (int) ($review->cycle?->rating_scale_max ?? 5),
            selfScore: $this->scoreLabel($review->self_score),
            managerScore: $this->scoreLabel($review->manager_score),
            finalScore: $this->scoreLabel($review->final_score),
            finalRating: $review->final_rating,
            formulaScore: $snapshot && $canViewTrace ? number_format((float) $snapshot->total_score, 2).' / 100' : null,
            formulaRating: $snapshot && $canViewTrace ? (string) ($band['label'] ?? Str::headline((string) $snapshot->score_band)) : null,
            scoringRuleVersion: $canViewTrace ? $snapshot?->rule_version : null,
            scoringRuleChecksum: $canViewTrace ? $snapshot?->scoringRule?->configuration_checksum : null,
            scoringCalculatedAt: $canViewTrace ? $snapshot?->calculated_at?->format('d M Y H:i') : null,
            scoreIsOverride: $canViewTrace && (bool) ($snapshot?->is_override ?? false),
            calculationTrace: $snapshot && $canViewTrace ? [
                'components' => $snapshot->component_scores ?? [],
                'weights' => $snapshot->applied_weights ?? [],
                'input_hash' => $snapshot->input_hash,
                'rule_version' => $snapshot->rule_version,
                'rule_checksum' => $snapshot->scoringRule?->configuration_checksum,
            ] : [],
            overrideRequestId: $canViewOverrideGovernance ? $pendingOverride?->id : null,
            overrideStatus: $canViewOverrideGovernance ? $pendingOverride?->status : null,
            overrideRequestedScore: $canViewOverrideGovernance && $pendingOverride ? number_format((float) $pendingOverride->requested_score, 2) : null,
            overrideRequester: $canViewOverrideGovernance ? $pendingOverride?->requestedBy?->name : null,
            status: $review->status,
            statusLabel: Str::headline($review->status),
            pipRequired: (bool) $review->pip_required,
            canSubmitSelf: $actor->can('submitSelf', $review),
            canSubmitManager: $actor->can('submitManager', $review),
            canCalibrate: $pendingOverride === null && $actor->can('calibrate', $review),
            canRequestOverride: $pendingOverride === null && $snapshot !== null && $snapshot->is_current
                && data_get($snapshot->scoringRule?->configuration, 'override.allowed', false)
                && $actor->can('requestOverride', $review),
            canDecideOverride: $pendingOverride !== null
                && (int) $pendingOverride->requested_by_user_id !== (int) $actor->id
                && $actor->can('approveOverride', $review),
            canClose: $snapshot !== null && $pendingOverride === null && $actor->can('close', $review),
            );
        });
    }

    public function companies(User $actor): Collection
    {
        return $this->scope->apply(Company::query()->where('status', 'active'), $actor, 'id')->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function projects(User $actor): Collection
    {
        return $this->scope->apply(Project::query(), $actor)->orderBy('name')->get(['id', 'code', 'name', 'company_id']);
    }

    public function employees(User $actor): Collection
    {
        $query = Employee::query()->where('status', 'active')->orderBy('name');
        $this->scope->apply($query, $actor);

        return $query->get(['id', 'employee_code', 'name', 'designation', 'department', 'manager_employee_id', 'company_id']);
    }

    public function departments(User $actor): Collection
    {
        $query = Employee::query()->whereNotNull('department')->distinct()->orderBy('department');
        $this->scope->apply($query, $actor);

        return $query->pluck('department');
    }

    public function activeCycles(User $actor): Collection
    {
        $query = PerformanceCycle::query()->where('status', 'active')->orderByDesc('starts_on');
        $this->scope->apply($query, $actor);

        return $query->get(['id', 'cycle_code', 'name', 'department', 'project_id', 'company_id', 'rating_scale_min', 'rating_scale_max']);
    }

    private function cycleQuery(User $actor): Builder
    {
        $query = PerformanceCycle::query();
        $this->scope->apply($query, $actor, 'performance_cycles.company_id');

        return $query;
    }

    private function reviewQuery(User $actor): Builder
    {
        $employee = $actor->employee;
        $query = PerformanceReview::query()
            ->when(! $actor->hasPermission('performance.view') && ! $actor->hasPermission('performance.manage') && ! $actor->hasPermission('performance.approve') && ! $actor->hasPermission('*'), fn (Builder $query) => $query->where('performance_reviews.employee_id', $employee?->id ?? 0))
            ->when($actor->hasPermission('performance.manage') && ! $actor->hasPermission('hr.manage') && ! $actor->hasPermission('performance.approve') && ! $actor->hasPermission('*'), function (Builder $query) use ($employee): void {
                $query->where(fn (Builder $nested) => $nested->where('performance_reviews.employee_id', $employee?->id ?? 0)->orWhere('performance_reviews.manager_employee_id', $employee?->id ?? 0));
            });
        $this->scope->apply($query, $actor, 'performance_reviews.company_id');

        return $query;
    }

    private function scoreLabel(mixed $score): ?string
    {
        return $score === null ? null : number_format((float) $score, 2);
    }
}
