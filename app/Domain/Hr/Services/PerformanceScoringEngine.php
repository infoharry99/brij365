<?php

namespace App\Domain\Hr\Services;

use App\Application\Scoring\DTOs\ScoreCalculationResultData;
use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Domain\Scoring\Services\ScoreSnapshotWriter;
use App\Domain\Scoring\Services\ScoringSubjectRegistry;
use App\Domain\Scoring\Services\ScoringRoundingPolicy;
use App\Domain\Scoring\Services\StructuredScoreCalculator;
use App\Models\PerformanceReview;
use App\Models\ScoringRule;
use App\Models\ScoreSnapshot;
use Illuminate\Validation\ValidationException;

final readonly class PerformanceScoringEngine
{
    public function __construct(
        private ActiveScoringRuleResolver $rules,
        private ScoringSubjectRegistry $subjects,
        private StructuredScoreCalculator $calculator,
        private ScoreSnapshotWriter $snapshots,
        private ScoringRoundingPolicy $rounding,
        private PerformanceAttendanceEvidenceResolver $attendanceEvidence,
        private PerformanceScoringEvidenceResolver $performanceEvidence,
    ) {}

    public function calculateAndPin(PerformanceReview $review): ScoreSnapshot
    {
        $review->loadMissing('cycle');
        $rule = $this->rules->resolve((int) $review->company_id, 'employee_performance');

        if ($rule === null) {
            throw ValidationException::withMessages([
                'scoring_rule' => 'Activate an approved Employee Performance scoring rule before HR calibration.',
            ]);
        }

        $review = $this->attendanceEvidence->synchronize($review, $rule);

        $subject = $this->subjects->subject($rule, $review->fresh(['cycle']));
        $result = $this->calculator->calculate($rule, $subject->inputs);
        $snapshot = $this->snapshots->write(
            $rule,
            $result,
            $subject->type,
            $subject->id,
            $subject->inputs,
            array_merge($subject->metadata, [
                'calculation_authority' => 'performance_scoring_engine',
                'configuration_checksum' => $rule->configuration_checksum,
            ]),
        );

        return $snapshot->load('scoringRule');
    }

    /**
     * Calculate a what-if result from normalized 0-100 criterion scores.
     * This path deliberately bypasses the snapshot writer and review model so
     * simulations cannot become performance evidence or mutate workflow state.
     *
     * @param  array<string, float>  $criterionScores
     */
    public function simulate(ScoringRule $rule, array $criterionScores): ScoreCalculationResultData
    {
        if ($rule->rule_key !== 'employee_performance') {
            throw ValidationException::withMessages([
                'scoring_rule' => 'Only Employee Performance rule versions can use this simulation.',
            ]);
        }

        $criteria = collect((array) data_get($rule->configuration, 'criteria', []));
        $unknown = collect(array_keys($criterionScores))->diff(
            $criteria->pluck('key')->map(static fn (mixed $key): string => (string) $key),
        );
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'criterion_scores' => 'One or more supplied criteria do not belong to the selected rule version.',
            ]);
        }

        $calculatorInputs = $criteria->mapWithKeys(static function (mixed $criterion) use ($criterionScores): array {
            if (! is_array($criterion)) {
                return [];
            }

            $key = (string) ($criterion['key'] ?? '');
            if ($key === '' || ! array_key_exists($key, $criterionScores)) {
                return [];
            }

            $normalizedScore = (float) $criterionScores[$key];
            if ($normalizedScore < 0 || $normalizedScore > 100) {
                throw ValidationException::withMessages([
                    'criterion_scores.'.$key => 'A normalized criterion score must be between 0 and 100.',
                ]);
            }

            return [$key => ($normalizedScore / 100) * (float) ($criterion['max_points'] ?? 0)];
        })->all();

        return $this->calculator->calculate($rule, $calculatorInputs);
    }

    public function pinnedSnapshot(PerformanceReview $review): ScoreSnapshot
    {
        $snapshot = $review->scoreSnapshot()->with('scoringRule')->first();

        if ($snapshot === null
            || $snapshot->subject_type !== PerformanceReview::class
            || (int) $snapshot->subject_id !== (int) $review->id
            || (int) $snapshot->company_id !== (int) $review->company_id
            || $snapshot->scoringRule?->rule_key !== 'employee_performance') {
            throw ValidationException::withMessages([
                'score_snapshot' => 'Complete HR calibration and calculate a governed score before closing this review.',
            ]);
        }

        return $snapshot;
    }

    /** @return array{normalized_score: float, cycle_score: float, rating: string, pip_required: bool, pip_threshold: ?float} */
    public function finalization(PerformanceReview $review): array
    {
        $snapshot = $this->pinnedSnapshot($review);
        $this->attendanceEvidence->assertCurrent($review, $snapshot->scoringRule, $snapshot);
        $this->performanceEvidence->assertCurrent($review, $snapshot->scoringRule, $snapshot);
        $configuration = $snapshot->scoringRule?->configuration ?? [];
        $normalizedScore = (float) $snapshot->total_score;
        $ratingMinimum = (float) data_get($configuration, 'rating_scale.min', 1);
        $ratingMaximum = (float) data_get($configuration, 'rating_scale.max', 5);
        $cycleScore = $this->rounding->normalizedToRange(
            $normalizedScore,
            $ratingMinimum,
            $ratingMaximum,
            $configuration,
        );
        $band = collect($configuration['bands'] ?? [])->first(
            static fn (array $candidate): bool => ($candidate['key'] ?? null) === $snapshot->score_band,
        );
        $pipThreshold = data_get($configuration, 'thresholds.pip_score');
        $pipThreshold = is_numeric($pipThreshold) ? (float) $pipThreshold : null;

        return [
            'normalized_score' => $normalizedScore,
            'cycle_score' => $cycleScore,
            'rating' => (string) ($band['label'] ?? str((string) $snapshot->score_band)->headline()),
            'pip_required' => $pipThreshold !== null && $normalizedScore <= $pipThreshold,
            'pip_threshold' => $pipThreshold,
        ];
    }
}
