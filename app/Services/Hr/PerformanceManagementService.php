<?php

namespace App\Services\Hr;

use App\Application\Hr\Data\ManagerPerformanceReviewData;
use App\Domain\Hr\Services\PerformanceScoringEngine;
use App\Domain\Hr\Services\PerformanceReviewConcurrencyGuard;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerformanceManagementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly CompanyScopeService $companyScope,
        private readonly PerformanceScoringEngine $performanceScoring,
        private readonly PerformanceReviewConcurrencyGuard $performanceReviewConcurrency,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCycle(array $data, User $actor, ?Request $request = null): PerformanceCycle
    {
        return DB::transaction(function () use ($data, $actor, $request): PerformanceCycle {
            $companyId = $this->resolveCycleCompanyId($data, $actor);

            $this->assertNoOverlappingCycle($companyId, $data);

            $status = $data['status'] ?? 'active';
            $history = [
                $this->historyEvent($status === 'active' ? 'active' : 'draft', $actor, 'Performance cycle created.'),
            ];

            $cycle = PerformanceCycle::create([
                'company_id' => $companyId,
                'project_id' => $data['project_id'] ?? null,
                'created_by_user_id' => $actor->id,
                'activated_by_user_id' => $status === 'active' ? $actor->id : null,
                'cycle_code' => $this->nextCycleCode(),
                'name' => $data['name'],
                'frequency' => $data['frequency'],
                'status' => $status,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'review_due_on' => $data['review_due_on'] ?? null,
                'department' => $data['department'] ?? null,
                'rating_scale_min' => $data['rating_scale_min'] ?? 1,
                'rating_scale_max' => $data['rating_scale_max'] ?? 5,
                'passing_score' => $data['passing_score'] ?? 3,
                'rules' => $data['rules'] ?? [
                    'kpi_weight_percent' => 70,
                    'kra_weight_percent' => 30,
                    'pip_threshold' => 2.5,
                ],
                'workflow_history' => $history,
                'activated_at' => $status === 'active' ? now() : null,
            ]);

            $this->auditLogger->record(
                $actor,
                'hr.performance_cycle.created',
                'Created performance review cycle',
                $cycle,
                [
                    'cycle_code' => $cycle->cycle_code,
                    'frequency' => $cycle->frequency,
                    'department' => $cycle->department,
                    'project_id' => $cycle->project_id,
                ],
                $request,
            );

            return $cycle->load($this->cycleRelations())->loadCount('reviews');
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createReview(array $data, User $actor, ?Request $request = null): PerformanceReview
    {
        return DB::transaction(function () use ($data, $actor, $request): PerformanceReview {
            $cycle = PerformanceCycle::query()
                ->whereKey($data['performance_cycle_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->companyScope->allows($actor, $cycle->company_id)) {
                throw ValidationException::withMessages(['performance_cycle_id' => 'The selected performance cycle is outside your company scope.']);
            }

            if ($cycle->status !== 'active') {
                throw ValidationException::withMessages(['performance_cycle_id' => 'Reviews can be created only for active performance cycles.']);
            }

            $employee = Employee::query()
                ->whereKey($data['employee_id'])
                ->firstOrFail();

            if (! $this->companyScope->allows($actor, $employee->company_id)) {
                throw ValidationException::withMessages(['employee_id' => 'The selected employee is outside your company scope.']);
            }

            if ((int) $cycle->company_id !== (int) $employee->company_id) {
                throw ValidationException::withMessages(['employee_id' => 'The employee does not belong to the selected performance cycle company.']);
            }

            $manager = $this->resolveManager($employee, $data['manager_employee_id'] ?? null);

            if (! $actor->hasPermission('hr.manage') && ! $actor->hasPermission('*')) {
                $actorEmployee = $actor->employee;
                if (! $actorEmployee || (int) $manager?->id !== (int) $actorEmployee->id) {
                    throw ValidationException::withMessages(['employee_id' => 'Managers can create reviews only for their direct reports.']);
                }
            }

            if ($cycle->department && $employee->department !== $cycle->department) {
                throw ValidationException::withMessages(['employee_id' => 'The employee does not belong to the cycle department.']);
            }

            if ($cycle->project_id && (int) $employee->project_id !== (int) $cycle->project_id) {
                throw ValidationException::withMessages(['employee_id' => 'The employee does not belong to the cycle project.']);
            }

            if (PerformanceReview::query()->where('performance_cycle_id', $cycle->id)->where('employee_id', $employee->id)->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'A review already exists for this employee in the selected cycle.']);
            }

            $this->assertKpiWeights($data['kpis']);

            $review = PerformanceReview::create([
                'company_id' => $cycle->company_id,
                'performance_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'manager_employee_id' => $manager?->id,
                'review_number' => $this->nextReviewNumber(),
                'status' => 'draft',
                'legacy_manual_scoring' => false,
                'period_start' => $cycle->starts_on,
                'period_end' => $cycle->ends_on,
                'kpis' => $data['kpis'],
                'kra_summary' => $data['kra_summary'] ?? [],
                'workflow_history' => [
                    $this->historyEvent('draft', $actor, 'Performance review created.'),
                ],
            ]);

            if ($employee->user) {
                $this->notifications->sendToUser($employee->user, [
                    'category' => 'performance',
                    'severity' => 'info',
                    'title' => 'Performance review opened',
                    'body' => "Your {$cycle->name} performance review is ready for self-review.",
                    'action_url' => route('hr.performance-reviews.index', ['employee_id' => $employee->id], false),
                    'payload' => ['review_number' => $review->review_number],
                ], $actor, $review);
            }

            $this->auditLogger->record(
                $actor,
                'hr.performance_review.created',
                'Created employee performance review',
                $review,
                [
                    'review_number' => $review->review_number,
                    'cycle_code' => $cycle->cycle_code,
                    'employee_code' => $employee->employee_code,
                ],
                $request,
            );

            return $review->load($this->reviewRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitSelfReview(PerformanceReview $performanceReview, array $data, User $actor, ?Request $request = null): PerformanceReview
    {
        return DB::transaction(function () use ($performanceReview, $data, $actor, $request): PerformanceReview {
            $review = PerformanceReview::query()->whereKey($performanceReview->id)->lockForUpdate()->firstOrFail();

            if ($review->status !== 'draft') {
                throw ValidationException::withMessages(['review' => 'Self-review can be submitted only while the review is in draft status.']);
            }

            $this->assertScoreWithinScale($review->cycle, (float) $data['self_score'], 'self_score');

            $history = $review->workflow_history ?? [];
            $history[] = $this->historyEvent('self_submitted', $actor, 'Employee self-review submitted.');

            $review->forceFill([
                'status' => 'self_submitted',
                'self_reviewer_user_id' => $actor->id,
                'self_score' => $data['self_score'],
                'kra_summary' => array_replace($review->kra_summary ?? [], $data['kra_summary'] ?? []),
                'strengths' => $data['strengths'] ?? $review->strengths,
                'improvement_areas' => $data['improvement_areas'] ?? $review->improvement_areas,
                'self_submitted_at' => now(),
                'workflow_history' => $history,
            ])->save();

            if ($review->managerEmployee?->user) {
                $this->notifications->sendToUser($review->managerEmployee->user, [
                    'category' => 'performance',
                    'severity' => 'info',
                    'title' => 'Self-review submitted',
                    'body' => "{$review->employee->name} submitted a self-review for {$review->cycle->name}.",
                    'action_url' => route('hr.performance-reviews.index', ['employee_id' => $review->employee_id], false),
                    'payload' => ['review_number' => $review->review_number],
                ], $actor, $review);
            }

            $this->auditLogger->record(
                $actor,
                'hr.performance_review.self_submitted',
                'Submitted performance self-review',
                $review,
                ['review_number' => $review->review_number, 'self_score' => $review->self_score],
                $request,
            );

            return $review->load($this->reviewRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitManagerReview(PerformanceReview $performanceReview, array $data, User $actor, ?Request $request = null): PerformanceReview
    {
        return DB::transaction(function () use ($performanceReview, $data, $actor, $request): PerformanceReview {
            $review = PerformanceReview::query()->whereKey($performanceReview->id)->lockForUpdate()->firstOrFail();

            if (! in_array($review->status, ['draft', 'self_submitted'], true)) {
                throw ValidationException::withMessages(['review' => 'Manager review can be submitted only before HR closure.']);
            }

            $this->assertScoreWithinScale($review->cycle, (float) $data['manager_score'], 'manager_score');

            if (isset($data['kpis'])) {
                $this->assertKpiWeights($data['kpis']);
            }
            $managerScoringInputs = collect($data['scoring_inputs'] ?? [])
                ->only(ManagerPerformanceReviewData::ALLOWED_SCORING_INPUTS)
                ->all();

            foreach ($managerScoringInputs as $key => $score) {
                $this->assertScoreWithinScale($review->cycle, (float) $score, 'scoring_inputs.'.$key);
            }

            $history = $review->workflow_history ?? [];
            $history[] = $this->historyEvent('manager_submitted', $actor, 'Manager review submitted.');

            $review->forceFill([
                'status' => 'manager_submitted',
                'manager_reviewer_user_id' => $actor->id,
                'manager_score' => $data['manager_score'],
                'kpis' => $data['kpis'] ?? $review->kpis,
                'scoring_inputs' => array_replace($review->scoring_inputs ?? [], $managerScoringInputs, [
                    'self_review' => $review->self_score !== null
                        ? (float) $review->self_score
                        : data_get($review->scoring_inputs, 'self_review'),
                    'manager_review' => (float) $data['manager_score'],
                ]),
                'manager_comments' => $data['manager_comments'],
                'manager_submitted_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->notifications->sendToPermission(['performance.approve', 'hr.manage'], [
                'category' => 'performance',
                'severity' => 'info',
                'title' => 'Performance review ready for HR closure',
                'body' => "{$review->employee->name}'s {$review->cycle->name} review is manager-submitted.",
                'action_url' => route('hr.performance-reviews.index', ['status' => 'manager_submitted'], false),
                'payload' => ['review_number' => $review->review_number],
            ], $actor, $review, $review->company_id);

            $this->auditLogger->record(
                $actor,
                'hr.performance_review.manager_submitted',
                'Submitted manager performance review',
                $review,
                ['review_number' => $review->review_number, 'manager_score' => $review->manager_score],
                $request,
            );

            return $review->load($this->reviewRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function closeReview(PerformanceReview $performanceReview, array $data, User $actor, ?Request $request = null): PerformanceReview
    {
        return DB::transaction(function () use ($performanceReview, $data, $actor, $request): PerformanceReview {
            $review = PerformanceReview::query()->whereKey($performanceReview->id)->lockForUpdate()->firstOrFail();
            $this->performanceReviewConcurrency->assertCurrent($review, (int) $data['lock_version']);

            if ($review->status !== 'manager_submitted') {
                throw ValidationException::withMessages(['review' => 'Only manager-submitted reviews can be closed by HR.']);
            }

            $history = $review->workflow_history ?? [];
            $history[] = $this->historyEvent('closed', $actor, 'HR closed the performance review.');

            $formula = null;
            if ($review->score_snapshot_id !== null) {
                if ($review->scoreOverrideRequests()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'score_snapshot' => 'Decide the pending score override before closing this review.',
                    ]);
                }
                $review->loadMissing(['cycle', 'scoreSnapshot.scoringRule']);
                $formula = $this->performanceScoring->finalization($review);
                $finalScore = $formula['cycle_score'];
                $finalRating = $formula['rating'];
                $pipThreshold = $formula['pip_threshold'];
            } else {
                if (! $review->legacy_manual_scoring) {
                    throw ValidationException::withMessages([
                        'score_snapshot' => 'Complete governed HR calibration before closing this review. Manual browser-supplied final scores are not authoritative.',
                    ]);
                }
                if (! isset($data['final_score'], $data['final_rating'])) {
                    throw ValidationException::withMessages([
                        'score_snapshot' => 'Complete governed HR calibration before closing this review.',
                    ]);
                }
                $this->assertScoreWithinScale($review->cycle, (float) $data['final_score'], 'final_score');
                $finalScore = (float) $data['final_score'];
                $finalRating = (string) $data['final_rating'];
                $pipThreshold = $this->pipThreshold($review->cycle);
            }
            $pipRequired = (bool) ($data['pip_required'] ?? false);

            if (($formula !== null && $formula['pip_required'])
                || ($formula === null && $pipThreshold !== null && $finalScore <= $pipThreshold)) {
                $pipRequired = true;
            }

            if ($pipRequired && ! $this->hasMeaningfulPipPlan($data['pip_plan'] ?? null)) {
                throw ValidationException::withMessages([
                    'pip_plan' => 'A PIP plan is required when the final score meets the cycle PIP threshold.',
                ]);
            }

            $review->forceFill([
                'status' => 'closed',
                'hr_reviewer_user_id' => $actor->id,
                'final_score' => $finalScore,
                'final_rating' => $finalRating,
                'hr_comments' => $data['hr_comments'],
                'pip_required' => $pipRequired,
                'pip_status' => $pipRequired ? 'open' : null,
                'pip_plan' => $pipRequired ? $data['pip_plan'] : null,
                'closed_at' => now(),
                'workflow_history' => $history,
                'lock_version' => $this->performanceReviewConcurrency->nextVersion($review),
            ])->save();

            if ($review->employee?->user) {
                $this->notifications->sendToUser($review->employee->user, [
                    'category' => 'performance',
                    'severity' => $pipRequired ? 'warning' : 'info',
                    'title' => $pipRequired ? 'Performance review closed with PIP' : 'Performance review closed',
                    'body' => "Your {$review->cycle->name} performance review has been closed.",
                    'action_url' => route('hr.performance-reviews.index', ['employee_id' => $review->employee_id], false),
                    'payload' => ['review_number' => $review->review_number, 'pip_required' => $pipRequired],
                ], $actor, $review);
            }

            $this->auditLogger->record(
                $actor,
                'hr.performance_review.closed',
                'Closed performance review',
                $review,
                [
                    'review_number' => $review->review_number,
                    'final_score' => $review->final_score,
                    'final_rating' => $review->final_rating,
                    'pip_required' => $review->pip_required,
                    'pip_threshold' => $pipThreshold,
                    'normalized_score' => $formula['normalized_score'] ?? null,
                    'score_snapshot_id' => $review->score_snapshot_id,
                ],
                $request,
            );

            return $review->load($this->reviewRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertNoOverlappingCycle(int $companyId, array $data): void
    {
        $overlapExists = PerformanceCycle::query()
            ->where('company_id', $companyId)
            ->where('frequency', $data['frequency'])
            ->where('department', $data['department'] ?? null)
            ->where('project_id', $data['project_id'] ?? null)
            ->whereIn('status', ['draft', 'active'])
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'starts_on' => 'A performance cycle already overlaps this period for the same frequency and population.',
            ]);
        }
    }

    private function resolveManager(Employee $employee, ?int $managerEmployeeId): ?Employee
    {
        $managerId = $managerEmployeeId ?: $employee->manager_employee_id;

        if (! $managerId) {
            return null;
        }

        $manager = Employee::query()
            ->where('company_id', $employee->company_id)
            ->whereKey($managerId)
            ->firstOrFail();

        if ((int) $manager->id === (int) $employee->id) {
            throw ValidationException::withMessages(['manager_employee_id' => 'An employee cannot be their own performance manager.']);
        }

        return $manager;
    }

    /**
     * @param array<int, array<string, mixed>> $kpis
     */
    private function assertKpiWeights(array $kpis): void
    {
        $weight = collect($kpis)->sum(fn (array $kpi): float => (float) ($kpi['weight'] ?? 0));

        if (round($weight, 2) !== 100.0) {
            throw ValidationException::withMessages(['kpis' => 'KPI weights must total exactly 100%.']);
        }
    }

    private function assertScoreWithinScale(PerformanceCycle $cycle, float $score, string $field): void
    {
        if ($score < $cycle->rating_scale_min || $score > $cycle->rating_scale_max) {
            throw ValidationException::withMessages([
                $field => "The score must be between {$cycle->rating_scale_min} and {$cycle->rating_scale_max} for this cycle.",
            ]);
        }
    }

    private function pipThreshold(PerformanceCycle $cycle): ?float
    {
        $threshold = data_get($cycle->rules ?? [], 'pip_threshold');

        return is_numeric($threshold) ? (float) $threshold : null;
    }

    private function hasMeaningfulPipPlan(mixed $plan): bool
    {
        if (! is_array($plan)) {
            return false;
        }

        $objectives = collect($plan['objectives'] ?? [])
            ->filter(static fn (mixed $objective): bool => is_string($objective) && trim($objective) !== '');

        return $objectives->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    private function historyEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveCycleCompanyId(array $data, User $actor): int
    {
        if (isset($data['project_id'])) {
            $projectCompanyId = \App\Models\Project::query()->whereKey($data['project_id'])->value('company_id');

            if ($projectCompanyId && $this->companyScope->allows($actor, $projectCompanyId)) {
                return (int) $projectCompanyId;
            }
        }

        if (isset($data['company_id']) && $this->companyScope->allows($actor, $data['company_id'])) {
            return (int) $data['company_id'];
        }

        $companyId = $this->companyScope->companyIdFor($actor);

        if ($companyId === null || $companyId === 0) {
            throw ValidationException::withMessages(['company_id' => 'A company is required to create a performance cycle.']);
        }

        return $companyId;
    }

    private function nextCycleCode(): string
    {
        return sprintf('PFC-%05d', PerformanceCycle::query()->withTrashed()->count() + 10001);
    }

    private function nextReviewNumber(): string
    {
        return sprintf('PFR-%05d', PerformanceReview::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<int, string>
     */
    public function cycleRelations(): array
    {
        return ['company', 'project', 'createdBy', 'activatedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function reviewRelations(): array
    {
        return [
            'cycle', 'employee.user', 'managerEmployee.user', 'selfReviewer', 'managerReviewer', 'hrReviewer',
            'scoreSnapshot.scoringRule',
            'scoreOverrideRequests' => fn ($query) => $query->latest('id'),
            'scoreOverrideRequests.requestedBy', 'scoreOverrideRequests.decidedBy',
        ];
    }
}
