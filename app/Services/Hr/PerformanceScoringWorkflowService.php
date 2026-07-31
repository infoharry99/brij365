<?php

namespace App\Services\Hr;

use App\Domain\Hr\Services\PerformanceScoringEngine;
use App\Domain\Hr\Services\PerformanceReviewConcurrencyGuard;
use App\Domain\Scoring\Services\ScoreOverrideService;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\PerformanceReview;
use App\Models\PerformanceScoreOverrideRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PerformanceScoringWorkflowService
{
    public function __construct(
        private PerformanceScoringEngine $engine,
        private PerformanceReviewConcurrencyGuard $concurrency,
        private ScoreOverrideService $overrides,
        private AuditLogger $audit,
        private NotificationCenterService $notifications,
    ) {}

    public function calibrate(PerformanceReview $performanceReview, float $score, string $comments, int $expectedVersion, User $actor, Request $request): PerformanceReview
    {
        return DB::transaction(function () use ($performanceReview, $score, $comments, $expectedVersion, $actor, $request): PerformanceReview {
            $review = PerformanceReview::query()->with('cycle')->whereKey($performanceReview->id)->lockForUpdate()->firstOrFail();
            $this->concurrency->assertCurrent($review, $expectedVersion);

            if ($review->status !== 'manager_submitted') {
                throw ValidationException::withMessages(['review' => 'HR calibration is available only after manager submission.']);
            }

            $minimum = (float) ($review->cycle?->rating_scale_min ?? 1);
            $maximum = (float) ($review->cycle?->rating_scale_max ?? 5);
            if ($score < $minimum || $score > $maximum) {
                throw ValidationException::withMessages([
                    'hr_calibration' => "The HR calibration score must be between {$minimum} and {$maximum}.",
                ]);
            }

            $history = $review->workflow_history ?? [];
            $history[] = $this->history('score_calculated', $actor, 'HR calibration submitted and governed score calculated.');
            $review->forceFill([
                'scoring_inputs' => array_replace($review->scoring_inputs ?? [], ['hr_calibration' => $score]),
                'hr_reviewer_user_id' => $actor->id,
                'hr_comments' => trim($comments),
                'workflow_history' => $history,
                'lock_version' => $this->concurrency->nextVersion($review),
            ])->save();

            $snapshot = $this->engine->calculateAndPin($review);
            $this->audit->record($actor, 'hr.performance_review.score_calculated', 'Calculated governed performance score', $review, [
                'review_number' => $review->review_number,
                'score_snapshot_id' => $snapshot->id,
                'rule_version' => $snapshot->rule_version,
                'rule_checksum' => $snapshot->scoringRule?->configuration_checksum,
                'normalized_score' => (float) $snapshot->total_score,
            ], $request);

            return $review->fresh($this->relations());
        });
    }

    /** @param array{requested_score: mixed, reason: mixed, evidence?: mixed} $data */
    public function requestOverride(PerformanceReview $performanceReview, array $data, int $expectedVersion, User $actor, Request $request): PerformanceScoreOverrideRequest
    {
        return DB::transaction(function () use ($performanceReview, $data, $expectedVersion, $actor, $request): PerformanceScoreOverrideRequest {
            $review = PerformanceReview::query()->with('scoreSnapshot.scoringRule')->whereKey($performanceReview->id)->lockForUpdate()->firstOrFail();
            $this->concurrency->assertCurrent($review, $expectedVersion);

            if ($review->status !== 'manager_submitted') {
                throw ValidationException::withMessages(['review' => 'Only an open manager-submitted review can request a score override.']);
            }

            $snapshot = $this->engine->pinnedSnapshot($review);
            if (! $snapshot->is_current || ! data_get($snapshot->scoringRule?->configuration, 'override.allowed', false)) {
                throw ValidationException::withMessages(['requested_score' => 'This governed score is not available for override.']);
            }

            if (PerformanceScoreOverrideRequest::query()->where('performance_review_id', $review->id)->where('status', 'pending')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['requested_score' => 'A score override is already awaiting a separate approver.']);
            }

            $override = PerformanceScoreOverrideRequest::create([
                'company_id' => $review->company_id,
                'performance_review_id' => $review->id,
                'score_snapshot_id' => $snapshot->id,
                'requested_by_user_id' => $actor->id,
                'requested_score' => $data['requested_score'],
                'reason' => trim((string) $data['reason']),
                'evidence' => filled($data['evidence'] ?? null) ? trim((string) $data['evidence']) : null,
                'status' => 'pending',
            ]);

            $history = $review->workflow_history ?? [];
            $history[] = $this->history('override_requested', $actor, 'Governed score override requested for separate approval.');
            $review->forceFill([
                'workflow_history' => $history,
                'lock_version' => $this->concurrency->nextVersion($review),
            ])->save();

            $this->notifications->sendToPermission([
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE,
                'performance.approve',
            ], [
                'category' => 'performance',
                'severity' => 'warning',
                'title' => 'Performance score override awaiting approval',
                'body' => "A governed score override was requested for {$review->review_number}.",
                'action_url' => route('hr.performance-reviews.index', ['status' => 'manager_submitted'], false),
                'payload' => ['review_number' => $review->review_number, 'override_request_id' => $override->id],
            ], $actor, $review, $review->company_id);

            $this->audit->record($actor, 'hr.performance_review.override_requested', 'Requested governed performance score override', $override, [
                'review_number' => $review->review_number,
                'score_snapshot_id' => $snapshot->id,
                'calculated_score' => (float) $snapshot->total_score,
                'requested_score' => (float) $override->requested_score,
            ], $request);

            return $override->load(['review', 'scoreSnapshot.scoringRule', 'requestedBy']);
        });
    }

    public function decide(PerformanceScoreOverrideRequest $overrideRequest, bool $approve, string $decisionReason, int $expectedVersion, User $actor, Request $request): PerformanceReview
    {
        return DB::transaction(function () use ($overrideRequest, $approve, $decisionReason, $expectedVersion, $actor, $request): PerformanceReview {
            $override = PerformanceScoreOverrideRequest::query()
                ->with(['review', 'scoreSnapshot.scoringRule', 'requestedBy'])
                ->whereKey($overrideRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($override->status !== 'pending') {
                throw ValidationException::withMessages(['override_request' => 'This override request has already been decided.']);
            }

            if ((int) $override->requested_by_user_id === (int) $actor->id) {
                throw ValidationException::withMessages(['override_request' => 'The requester cannot approve or reject their own override.']);
            }

            $review = PerformanceReview::query()->with('scoreSnapshot.scoringRule')->whereKey($override->performance_review_id)->lockForUpdate()->firstOrFail();
            $this->concurrency->assertCurrent($review, $expectedVersion);
            if ($review->status !== 'manager_submitted' || (int) $review->score_snapshot_id !== (int) $override->score_snapshot_id) {
                throw ValidationException::withMessages(['override_request' => 'The review score changed after this request was submitted. Recalculate before requesting another override.']);
            }

            $snapshot = null;
            if ($approve) {
                $snapshot = $this->overrides->override(
                    $override->scoreSnapshot,
                    (float) $override->requested_score,
                    $override->reason,
                    $actor,
                    $request,
                    $override->id,
                );
                $review->forceFill(['score_snapshot_id' => $snapshot->id])->save();
            }

            $override->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by_user_id' => $actor->id,
                'decision_reason' => trim($decisionReason),
                'decided_at' => now(),
            ])->save();

            $history = $review->workflow_history ?? [];
            $history[] = $this->history(
                $approve ? 'override_approved' : 'override_rejected',
                $actor,
                $approve ? 'Governed score override approved.' : 'Governed score override rejected.',
            );
            $review->forceFill([
                'workflow_history' => $history,
                'lock_version' => $this->concurrency->nextVersion($review),
            ])->save();

            if ($override->requestedBy) {
                $this->notifications->sendToUser($override->requestedBy, [
                    'category' => 'performance',
                    'severity' => $approve ? 'info' : 'warning',
                    'title' => $approve ? 'Performance score override approved' : 'Performance score override rejected',
                    'body' => "The override request for {$review->review_number} was ".($approve ? 'approved.' : 'rejected.'),
                    'action_url' => route('hr.performance-reviews.index', ['status' => 'manager_submitted'], false),
                    'payload' => ['review_number' => $review->review_number, 'override_request_id' => $override->id],
                ], $actor, $review);
            }

            $this->audit->record($actor, $approve ? 'hr.performance_review.override_approved' : 'hr.performance_review.override_rejected', $approve ? 'Approved governed performance score override' : 'Rejected governed performance score override', $override, [
                'review_number' => $review->review_number,
                'override_request_id' => $override->id,
                'resulting_snapshot_id' => $snapshot?->id,
            ], $request);

            return $review->fresh($this->relations());
        });
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['cycle', 'employee.user', 'managerEmployee.user', 'selfReviewer', 'managerReviewer', 'hrReviewer', 'scoreSnapshot.scoringRule', 'scoreOverrideRequests.requestedBy', 'scoreOverrideRequests.decidedBy'];
    }

    /** @return array<string, mixed> */
    private function history(string $status, User $actor, string $note): array
    {
        return ['status' => $status, 'actor_user_id' => $actor->id, 'actor' => $actor->name, 'note' => $note, 'at' => now()->toISOString()];
    }
}
