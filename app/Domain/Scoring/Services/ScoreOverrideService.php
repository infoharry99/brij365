<?php

namespace App\Domain\Scoring\Services;

use App\Models\PerformanceReview;
use App\Models\PerformanceScoreOverrideRequest;
use App\Models\ScoreSnapshot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScoreOverrideService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function override(
        ScoreSnapshot $snapshot,
        float $score,
        string $reason,
        User $actor,
        Request $request,
        ?int $approvedPerformanceOverrideRequestId = null,
    ): ScoreSnapshot
    {
        return DB::transaction(function () use ($snapshot, $score, $reason, $actor, $request, $approvedPerformanceOverrideRequestId): ScoreSnapshot {
            $locked = ScoreSnapshot::query()->with('scoringRule')->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_current || ! data_get($locked->scoringRule?->configuration, 'override.allowed', false)) {
                throw ValidationException::withMessages(['score' => 'This score is not available for override.']);
            }
            if ($score < 0 || $score > 100) {
                throw ValidationException::withMessages(['score' => 'The override score must be between 0 and 100.']);
            }
            if (data_get($locked->scoringRule?->configuration, 'override.reason_required', true) && mb_strlen(trim($reason)) < 12) {
                throw ValidationException::withMessages(['reason' => 'Provide a complete override reason.']);
            }

            $performanceApproval = null;
            if ($locked->scoringRule?->rule_key === 'employee_performance') {
                $performanceApproval = $approvedPerformanceOverrideRequestId === null ? null : PerformanceScoreOverrideRequest::query()
                    ->whereKey($approvedPerformanceOverrideRequestId)
                    ->where('score_snapshot_id', $locked->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($performanceApproval === null
                    || (int) $performanceApproval->requested_by_user_id === (int) $actor->id
                    || abs((float) $performanceApproval->requested_score - $score) > 0.0001) {
                    throw ValidationException::withMessages([
                        'score' => 'Employee performance overrides require a pending request and a different authorized approver.',
                    ]);
                }
            }

            $band = collect($locked->scoringRule->configuration['bands'] ?? [])->sortByDesc('min_score')
                ->first(static fn (array $candidate): bool => $score >= (float) $candidate['min_score']);
            $locked->markHistorical();
            $override = ScoreSnapshot::create([
                'company_id' => $locked->company_id, 'scoring_rule_id' => $locked->scoring_rule_id,
                'overridden_from_snapshot_id' => $locked->id, 'overridden_by_user_id' => $actor->id,
                'subject_type' => $locked->subject_type, 'subject_id' => $locked->subject_id,
                'total_score' => $score, 'component_scores' => $locked->component_scores,
                'applied_weights' => $locked->applied_weights, 'score_band' => $band['key'] ?? null,
                'input_snapshot' => $locked->input_snapshot, 'input_hash' => $locked->input_hash,
                'rule_version' => $locked->rule_version, 'is_current' => true, 'is_override' => true,
                'override_reason' => trim($reason), 'overridden_at' => now(), 'calculated_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], [
                    'original_score' => (float) $locked->total_score,
                    'performance_override_request_id' => $performanceApproval?->id,
                    'requested_by_user_id' => $performanceApproval?->requested_by_user_id,
                ]),
            ]);

            if ($locked->subject_type === PerformanceReview::class) {
                PerformanceReview::query()->whereKey($locked->subject_id)->update([
                    'score_snapshot_id' => $override->id,
                    'updated_at' => now(),
                ]);
            }

            $this->audit->record($actor, 'scoring.score.overridden', 'Overrode calculated score', $override, [
                'original_snapshot_id' => $locked->id, 'original_score' => (float) $locked->total_score,
                'override_score' => $score, 'reason' => trim($reason),
            ], $request);
            return $override->load(['scoringRule', 'overriddenFrom', 'overriddenBy']);
        });
    }
}
