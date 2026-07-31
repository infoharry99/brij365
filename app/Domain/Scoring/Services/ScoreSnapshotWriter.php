<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\ScoreCalculationResultData;
use App\Domain\Hr\Services\PerformanceScoringEvidenceResolver;
use App\Models\ScoreSnapshot;
use App\Models\ScoringAggregateSubject;
use App\Models\ScoringRule;
use App\Models\PerformanceReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScoreSnapshotWriter
{
    public function __construct(private readonly PerformanceScoringEvidenceResolver $performanceEvidence) {}

    /** @param array<string, mixed> $inputs @param array<string, mixed> $metadata */
    public function write(ScoringRule $rule, ScoreCalculationResultData $result, string $subjectType, int $subjectId, array $inputs, array $metadata = []): ScoreSnapshot
    {
        return DB::transaction(function () use ($rule, $result, $subjectType, $subjectId, $inputs, $metadata): ScoreSnapshot {
            $this->lockSnapshotSubject($rule, $subjectType, $subjectId);

            $performanceReview = null;
            if ($subjectType === PerformanceReview::class) {
                $performanceReview = PerformanceReview::query()
                    ->whereKey($subjectId)
                    ->where('company_id', $rule->company_id)
                    ->lockForUpdate()
                    ->first();

                if ($performanceReview === null) {
                    throw ValidationException::withMessages([
                        'performance_review' => 'The performance review is unavailable in this scoring rule company.',
                    ]);
                }

                if (in_array($performanceReview->status, ['closed', 'cancelled'], true)) {
                    throw ValidationException::withMessages([
                        'performance_review' => 'Closed or cancelled performance reviews cannot be recalculated.',
                    ]);
                }

                if ($performanceReview->scoreOverrideRequests()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'performance_review' => 'Decide the pending performance score override before recalculation.',
                    ]);
                }

                if (array_key_exists('expected_score_snapshot_id', $metadata)
                    && $this->nullableInteger($metadata['expected_score_snapshot_id']) !== $this->nullableInteger($performanceReview->score_snapshot_id)) {
                    throw ValidationException::withMessages([
                        'performance_review' => 'The performance score changed while recalculation was in progress. Retry with the current review.',
                    ]);
                }

                if (isset($metadata['expected_scoring_inputs_hash'])
                    && ! hash_equals((string) $metadata['expected_scoring_inputs_hash'], $this->scoringInputsHash($performanceReview))) {
                    throw ValidationException::withMessages([
                        'performance_review' => 'The performance evidence changed while recalculation was in progress. Retry with the current review.',
                    ]);
                }

                if (isset($metadata['expected_performance_evidence_hash'])
                    && ! hash_equals(
                        (string) $metadata['expected_performance_evidence_hash'],
                        $this->performanceEvidence->snapshot($performanceReview, $rule)['hash'],
                    )) {
                    throw ValidationException::withMessages([
                        'performance_review' => 'The performance evidence changed while recalculation was in progress. Retry with the current review.',
                    ]);
                }
            }

            $currentSnapshots = ScoreSnapshot::query()->where('company_id', $rule->company_id)->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)->where('is_current', true)
                ->whereHas('scoringRule', static fn ($query) => $query->where('rule_key', $rule->rule_key))
                ->lockForUpdate()->get();

            foreach ($currentSnapshots as $currentSnapshot) {
                $currentSnapshot->markHistorical();
            }

            $snapshot = ScoreSnapshot::create([
                'company_id' => $rule->company_id, 'scoring_rule_id' => $rule->id,
                'subject_type' => $subjectType, 'subject_id' => $subjectId,
                'total_score' => $result->totalScore, 'component_scores' => $result->componentScores,
                'applied_weights' => $result->appliedWeights, 'score_band' => $result->scoreBand,
                'input_snapshot' => $inputs, 'input_hash' => $result->inputHash, 'rule_version' => $result->ruleVersion,
                'is_current' => true, 'is_override' => false, 'calculated_at' => now(),
                'metadata' => array_merge($metadata, ['mandatory_failures' => $result->mandatoryFailures]),
            ]);

            if ($performanceReview !== null) {
                $performanceReview->forceFill(['score_snapshot_id' => $snapshot->id])->save();
            }

            return $snapshot;
        });
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function scoringInputsHash(PerformanceReview $review): string
    {
        return hash('sha256', json_encode($review->scoring_inputs ?? [], JSON_THROW_ON_ERROR));
    }

    private function lockSnapshotSubject(ScoringRule $rule, string $subjectType, int $subjectId): void
    {
        $lockType = 'score_snapshot_mutex';
        $lockKey = hash('sha256', json_encode([
            'rule_key' => $rule->rule_key,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ], JSON_THROW_ON_ERROR));
        $timestamp = now();

        ScoringAggregateSubject::query()->insertOrIgnore([
            'company_id' => $rule->company_id,
            'subject_type' => $lockType,
            'subject_key' => $lockKey,
            'label' => 'Score snapshot write lock',
            'metadata' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        ScoringAggregateSubject::query()
            ->where('company_id', $rule->company_id)
            ->where('subject_type', $lockType)
            ->where('subject_key', $lockKey)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
