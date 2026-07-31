<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\ScoringSubjectData;
use App\Domain\Hr\Services\PerformanceScoringEvidenceResolver;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\Interview;
use App\Models\Lead;
use App\Models\PerformanceReview;
use App\Models\Project;
use App\Models\ScoringRule;
use App\Models\ScoringAggregateSubject;
use App\Models\ServiceTicket;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ScoringSubjectRegistry
{
    public function __construct(private readonly PerformanceScoringEvidenceResolver $performanceEvidence) {}

    public function eligibleQuery(ScoringRule $rule): Builder
    {
        $company = $rule->company_id;
        return match ($rule->rule_key) {
            'lead_quality' => Lead::query()->where('company_id', $company)->whereNotIn('status', ['won', 'lost']),
            'employee_performance' => PerformanceReview::query()
                ->where('company_id', $company)
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->whereDoesntHave('scoreOverrideRequests', static fn (Builder $query): Builder => $query->where('status', 'pending')),
            'employee_confirmation' => EmployeeConfirmationCase::query()->where('company_id', $company)->whereNull('hr_decided_at'),
            'recruitment_interview' => Interview::query()->where('company_id', $company)->whereNotIn('status', ['completed', 'cancelled']),
            'vendor_performance' => Vendor::query()->where('company_id', $company)->where('status', 'active'),
            'project_health' => Project::query()->where('company_id', $company)->whereNotIn('status', ['completed', 'cancelled']),
            'customer_satisfaction' => Project::query()->where('company_id', $company)->whereHas('serviceTickets', fn ($query) => $query->whereNotNull('customer_rating')),
            'exit_feedback' => $this->exitFeedbackSubjects($company),
            default => throw ValidationException::withMessages(['rule_key' => 'No recalculation source is registered for this scoring area.']),
        };
    }

    public function subject(ScoringRule $rule, Model $model): ScoringSubjectData
    {
        return match ($rule->rule_key) {
            'lead_quality' => $this->lead($model),
            'employee_confirmation' => $this->confirmation($rule, $model),
            'employee_performance' => $this->performance($rule, $model),
            'recruitment_interview' => $this->recruitment($rule, $model),
            'vendor_performance' => $this->vendor($rule, $model),
            'project_health' => $this->project($rule, $model),
            'customer_satisfaction' => $this->customerSatisfaction($rule, $model),
            'exit_feedback' => $this->exitFeedback($rule, $model),
            default => throw ValidationException::withMessages([
                'source_inputs' => 'This scoring area requires a completed source-input adapter before recalculation can run.',
            ]),
        };
    }

    private function exitFeedbackSubjects(int $companyId): Builder
    {
        EmployeeExitInterview::query()->with('employee:id,department')->where('company_id', $companyId)->whereNotNull('submitted_at')
            ->get()->pluck('employee.department')->filter()->unique()->each(static function (string $department) use ($companyId): void {
                ScoringAggregateSubject::updateOrCreate(
                    ['company_id' => $companyId, 'subject_type' => 'exit_feedback_department', 'subject_key' => str($department)->slug()->toString()],
                    ['label' => $department, 'metadata' => ['department' => $department]],
                );
            });
        return ScoringAggregateSubject::query()->where('company_id', $companyId)->where('subject_type', 'exit_feedback_department');
    }

    private function exitFeedback(ScoringRule $rule, Model $model): ScoringSubjectData
    {
        if (! $model instanceof ScoringAggregateSubject) {
            throw ValidationException::withMessages(['source_record' => 'The recalculation source record does not match Exit Feedback.']);
        }
        $department = (string) data_get($model->metadata, 'department');
        $interviews = EmployeeExitInterview::query()->where('company_id', $rule->company_id)->whereNotNull('submitted_at')
            ->whereHas('employee', fn ($query) => $query->where('department', $department))->get();
        $minimum = (int) ($rule->configuration['minimum_sample_size'] ?? 1);
        if ($interviews->count() < $minimum) {
            throw ValidationException::withMessages(['source_inputs' => "At least {$minimum} submitted exit interview(s) are required for this department summary."]);
        }
        $rows = $interviews->map(static fn (EmployeeExitInterview $interview): array => [
            'overall_experience' => $interview->overall_experience_rating,
            'manager_relationship' => $interview->manager_relationship_rating,
            'workload' => $interview->workload_rating,
            'compensation' => $interview->compensation_rating,
            'career_growth' => data_get($interview->scoring_inputs, 'career_growth'),
            'work_environment' => data_get($interview->scoring_inputs, 'work_environment'),
            'rehire_recommendation' => data_get($interview->scoring_inputs, 'rehire_recommendation'),
        ]);
        $keys = collect($rule->configuration['criteria'] ?? [])->pluck('key');
        if ($rows->contains(fn (array $row): bool => $keys->contains(fn (string $key): bool => ! isset($row[$key]) || ! is_numeric($row[$key])))) {
            throw ValidationException::withMessages(['source_inputs' => 'Submitted exit interviews are missing structured feedback ratings.']);
        }
        $ratings = $keys->mapWithKeys(fn (string $key): array => [$key => (float) $rows->avg($key)])->all();
        return $this->ratedInputs($rule, $model, $ratings, 5, ['department' => $department, 'sample_size' => $interviews->count()]);
    }

    private function customerSatisfaction(ScoringRule $rule, Model $model): ScoringSubjectData
    {
        if (! $model instanceof Project) {
            throw ValidationException::withMessages(['source_record' => 'The recalculation source record does not match Customer Satisfaction.']);
        }
        $tickets = $model->serviceTickets()->whereNotNull('customer_rating')->get(['id', 'customer_rating', 'scoring_inputs']);
        $minimum = (int) ($rule->configuration['minimum_sample_size'] ?? 1);
        if ($tickets->count() < $minimum) {
            throw ValidationException::withMessages(['source_inputs' => "At least {$minimum} rated service ticket(s) are required for this project summary."]);
        }
        $required = ['resolution_time', 'reopened_penalty', 'escalation_impact'];
        if ($tickets->contains(fn (ServiceTicket $ticket): bool => collect($required)->contains(fn (string $key): bool => ! isset($ticket->scoring_inputs[$key]) || ! is_numeric($ticket->scoring_inputs[$key])))) {
            throw ValidationException::withMessages(['source_inputs' => 'Rated service tickets are missing satisfaction impact evidence.']);
        }
        $percentages = [
            'customer_rating' => ((float) $tickets->avg('customer_rating') / 5) * 100,
            'resolution_time' => (float) $tickets->avg(fn (ServiceTicket $ticket): float => (float) $ticket->scoring_inputs['resolution_time']),
            'reopened_penalty' => (float) $tickets->avg(fn (ServiceTicket $ticket): float => (float) $ticket->scoring_inputs['reopened_penalty']),
            'escalation_impact' => (float) $tickets->avg(fn (ServiceTicket $ticket): float => (float) $ticket->scoring_inputs['escalation_impact']),
            'valid_sample' => 100,
        ];
        return $this->ratedInputs($rule, $model, $percentages, 100, [
            'project_code' => $model->code, 'sample_size' => $tickets->count(),
        ]);
    }

    private function project(ScoringRule $rule, Model $model): ScoringSubjectData
    {
        if (! $model instanceof Project) {
            throw ValidationException::withMessages(['source_record' => 'The recalculation source record does not match Project Health.']);
        }
        return $this->ratedInputs($rule, $model, $model->scoring_inputs ?? [], 100, [
            'project_code' => $model->code,
        ]);
    }

    private function vendor(ScoringRule $rule, Model $model): ScoringSubjectData
    {
        if (! $model instanceof Vendor) {
            throw ValidationException::withMessages(['source_record' => 'The recalculation source record does not match Vendor Performance.']);
        }
        return $this->ratedInputs($rule, $model, $model->scoring_inputs ?? [], 100, [
            'vendor_code' => $model->vendor_code,
        ]);
    }

    private function recruitment(ScoringRule $rule, Model $model): ScoringSubjectData
    {
        if (! $model instanceof Interview) {
            throw ValidationException::withMessages(['source_record' => 'The recalculation source record does not match Recruitment Interview.']);
        }
        $submittedCount = (int) data_get($model->feedback, 'summary.submitted_count', 0);
        $minimum = (int) ($rule->configuration['minimum_sample_size'] ?? 1);
        if ($submittedCount < $minimum) {
            throw ValidationException::withMessages(['source_inputs' => "At least {$minimum} panel response(s) are required before scoring."]);
        }
        return $this->ratedInputs($rule, $model, $model->scoring_inputs ?? [], 5, [
            'interview_code' => $model->interview_code, 'candidate_id' => $model->candidate_id,
            'panel_responses' => $submittedCount,
        ]);
    }

    /** @param array<string, mixed> $ratings @param array<string, mixed> $metadata */
    private function ratedInputs(ScoringRule $rule, Model $model, array $ratings, float $ratingMax, array $metadata): ScoringSubjectData
    {
        $criteria = collect($rule->configuration['criteria'] ?? []);
        $missing = $criteria->pluck('key')->reject(static fn (string $key): bool => isset($ratings[$key]) && is_numeric($ratings[$key]))->values()->all();
        if ($missing !== []) {
            throw ValidationException::withMessages(['source_inputs' => 'Structured ratings are incomplete for: '.implode(', ', $missing).'.']);
        }
        $inputs = $criteria->mapWithKeys(static fn (array $criterion): array => [
            $criterion['key'] => min((float) $criterion['max_points'], max(0, ((float) $ratings[$criterion['key']] / $ratingMax) * (float) $criterion['max_points'])),
        ])->all();
        return new ScoringSubjectData(type: $model::class, id: $model->getKey(), inputs: $inputs, metadata: $metadata);
    }

    private function performance(ScoringRule $rule, Model $model): ScoringSubjectData
    {
        if (! $model instanceof PerformanceReview) {
            throw ValidationException::withMessages(['source_record' => 'The recalculation source record does not match Employee Performance.']);
        }
        $ratings = $model->scoring_inputs ?? [];
        $criteria = collect($rule->configuration['criteria'] ?? []);
        $missing = $criteria->filter(static function (array $criterion) use ($ratings): bool {
            $source = (string) ($criterion['source'] ?? $criterion['key']);
            $missingInput = ! isset($ratings[$source]) || ! is_numeric($ratings[$source]);
            return $missingInput && (($criterion['required'] ?? true) || ($criterion['missing_data_behavior'] ?? 'block') === 'block');
        })->pluck('key')->values()->all();
        if ($missing !== []) {
            throw ValidationException::withMessages(['source_inputs' => 'Performance component ratings are incomplete for: '.implode(', ', $missing).'.']);
        }
        $inputs = $criteria->filter(static function (array $criterion) use ($ratings): bool {
            $source = (string) ($criterion['source'] ?? $criterion['key']);

            return isset($ratings[$source]) && is_numeric($ratings[$source]);
        })->mapWithKeys(fn (array $criterion): array => [
                $criterion['key'] => $this->normalizedPerformancePoints(
                    $criterion,
                    (float) $ratings[(string) ($criterion['source'] ?? $criterion['key'])],
                ),
            ])->all();
        $performanceEvidence = $this->performanceEvidence->snapshot($model, $rule);

        return new ScoringSubjectData(
            type: PerformanceReview::class, id: $model->id, inputs: $inputs,
            metadata: [
                'review_number' => $model->review_number,
                'employee_id' => $model->employee_id,
                'expected_score_snapshot_id' => $model->score_snapshot_id,
                'expected_scoring_inputs_hash' => hash(
                    'sha256',
                    json_encode($model->scoring_inputs ?? [], JSON_THROW_ON_ERROR),
                ),
                'attendance_evidence' => data_get($model->scoring_inputs, 'attendance_evidence'),
                'performance_evidence' => $performanceEvidence,
                'expected_performance_evidence_hash' => $performanceEvidence['hash'],
            ],
        );
    }

    /** @param array<string, mixed> $criterion */
    private function normalizedPerformancePoints(array $criterion, float $rawValue): float
    {
        $maximumPoints = (float) $criterion['max_points'];
        $inputScale = $criterion['input_scale'] ?? null;
        if (! is_array($inputScale)
            || ! is_numeric($inputScale['min'] ?? null)
            || ! is_numeric($inputScale['max'] ?? null)) {
            throw ValidationException::withMessages([
                'scoring_rule' => 'The activated Employee Performance rule is missing an explicit criterion input scale. Create and activate a corrected rule version before recalculation.',
            ]);
        }
        $inputMinimum = (float) $inputScale['min'];
        $inputMaximum = (float) $inputScale['max'];
        if ($inputMaximum <= $inputMinimum || $rawValue < $inputMinimum || $rawValue > $inputMaximum) {
            throw ValidationException::withMessages([
                'source_inputs.'.(string) ($criterion['source'] ?? $criterion['key'] ?? 'criterion') => "The source value must be between {$inputMinimum} and {$inputMaximum} for the activated criterion scale.",
            ]);
        }
        // The activated rule's explicit floor and ceiling are the calculation
        // authority. Review-cycle scales must never change a pinned formula.
        $points = (($rawValue - $inputMinimum) / ($inputMaximum - $inputMinimum)) * $maximumPoints;

        return min($maximumPoints, max(0.0, $points));
    }

    private function confirmation(ScoringRule $rule, Model $model): ScoringSubjectData
    {
        if (! $model instanceof EmployeeConfirmationCase) {
            throw ValidationException::withMessages(['source_record' => 'The recalculation source record does not match Employee Confirmation.']);
        }
        $ratings = $model->review_scores ?? [];
        $criteria = collect($rule->configuration['criteria'] ?? []);
        $missing = $criteria->pluck('key')->reject(static fn (string $key): bool => isset($ratings[$key]) && is_numeric($ratings[$key]))->values()->all();
        if ($missing !== []) {
            throw ValidationException::withMessages(['source_inputs' => 'Confirmation review ratings are incomplete for: '.implode(', ', $missing).'.']);
        }
        $ratingMax = (float) data_get($rule->configuration, 'rating_scale.max', 5);
        $inputs = $criteria->mapWithKeys(static fn (array $criterion): array => [
            $criterion['key'] => min((float) $criterion['max_points'], max(0, ((float) $ratings[$criterion['key']] / $ratingMax) * (float) $criterion['max_points'])),
        ])->all();

        return new ScoringSubjectData(
            type: EmployeeConfirmationCase::class, id: $model->id, inputs: $inputs,
            metadata: ['case_number' => $model->case_number, 'employee_id' => $model->employee_id],
        );
    }

    private function lead(Model $model): ScoringSubjectData
    {
        if (! $model instanceof Lead) {
            throw ValidationException::withMessages(['source_record' => 'The recalculation source record does not match Lead Scoring.']);
        }
        $qualification = $model->latestQualification()->first();
        if (! $qualification || collect(['budget_score', 'authority_score', 'need_score', 'timeline_score'])->contains(fn (string $field): bool => $qualification->{$field} === null)) {
            throw ValidationException::withMessages(['source_inputs' => 'Lead qualification component scores are incomplete.']);
        }

        return new ScoringSubjectData(
            type: Lead::class,
            id: $model->id,
            inputs: [
                'budget_fit' => (float) $qualification->budget_score,
                'decision_authority' => (float) $qualification->authority_score,
                'requirement_clarity' => (float) $qualification->need_score,
                'purchase_timeline' => (float) $qualification->timeline_score,
            ],
            metadata: ['lead_code' => $model->lead_code, 'qualification_id' => $qualification->id],
        );
    }
}
