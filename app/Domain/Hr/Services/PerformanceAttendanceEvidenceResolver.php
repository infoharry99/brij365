<?php

namespace App\Domain\Hr\Services;

use App\Models\PayrollAttendanceSnapshot;
use App\Models\PerformanceReview;
use App\Models\ScoringRule;
use App\Models\ScoreSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PerformanceAttendanceEvidenceResolver
{
    public function synchronize(PerformanceReview $review, ScoringRule $rule): PerformanceReview
    {
        if (! $this->usesAttendance($rule)) {
            return $review;
        }

        $resolved = $this->resolve($review, $rule);
        $inputs = $review->scoring_inputs ?? [];
        $inputs['attendance'] = $resolved['rating'];
        $inputs['attendance_evidence'] = $resolved['evidence'];
        $review->forceFill(['scoring_inputs' => $inputs])->save();

        return $review->refresh();
    }

    public function assertCurrent(PerformanceReview $review, ScoringRule $rule, ScoreSnapshot $snapshot): void
    {
        if (! $this->usesAttendance($rule)) {
            return;
        }

        $storedEvidence = data_get($review->scoring_inputs, 'attendance_evidence');
        $snapshotEvidence = data_get($snapshot->metadata, 'attendance_evidence');

        if (! is_array($storedEvidence)
            || ! is_array($snapshotEvidence)
            || ! $this->hasValidHash($storedEvidence)
            || ! $this->hasValidHash($snapshotEvidence)
            || ! $this->sameEvidence($storedEvidence, $snapshotEvidence)) {
            $this->throwStaleEvidence();
        }

        try {
            $currentEvidence = $this->resolve($review, $rule)['evidence'];
        } catch (ValidationException) {
            $this->throwStaleEvidence();
        }

        if (! $this->sameEvidence($storedEvidence, $currentEvidence)) {
            $this->throwStaleEvidence();
        }
    }

    private function usesAttendance(ScoringRule $rule): bool
    {
        return collect($rule->configuration['criteria'] ?? [])->contains(
            static fn (array $candidate): bool => (string) ($candidate['source'] ?? $candidate['key'] ?? '') === 'attendance',
        );
    }

    /** @return array{rating: float, evidence: array<string, mixed>} */
    private function resolve(PerformanceReview $review, ScoringRule $rule): array
    {
        $snapshots = PayrollAttendanceSnapshot::query()
            ->with('periodLock:id,status,version,source_hash,lock_version')
            ->where('company_id', $review->company_id)
            ->where('employee_id', $review->employee_id)
            ->whereDate('period_start', '<=', $review->period_end)
            ->whereDate('period_end', '>=', $review->period_start)
            ->whereHas('periodLock', static fn ($query) => $query->where('status', 'finalized'))
            ->orderBy('period_start')
            ->orderBy('period_end')
            ->orderBy('id')
            ->get();

        if (! $this->coversReviewPeriod($snapshots, $review)) {
            return $this->legacyEvidence($review);
        }

        $scheduledDays = (float) $snapshots->sum(static fn (PayrollAttendanceSnapshot $snapshot): float => (float) $snapshot->scheduled_days);
        if ($scheduledDays <= 0) {
            throw ValidationException::withMessages([
                'scoring_inputs.attendance' => 'Finalized attendance evidence must contain scheduled days before governed performance scoring.',
            ]);
        }

        $payableDays = (float) $snapshots->sum(static fn (PayrollAttendanceSnapshot $snapshot): float => (float) $snapshot->payable_days);
        $attendancePercentage = round(min(100, max(0, ($payableDays / $scheduledDays) * 100)), 4, PHP_ROUND_HALF_UP);
        $ratingMaximum = (float) ($review->cycle?->rating_scale_max ?? data_get($rule->configuration, 'rating_scale.max', 5));
        $attendanceRating = round(($attendancePercentage / 100) * $ratingMaximum, 4, PHP_ROUND_HALF_UP);

        $evidence = [
            'source_type' => 'finalized_attendance_snapshots',
            'period_start' => $review->period_start?->toDateString(),
            'period_end' => $review->period_end?->toDateString(),
            'scheduled_days' => round($scheduledDays, 2),
            'payable_days' => round($payableDays, 2),
            'worked_minutes' => (int) $snapshots->sum('worked_minutes'),
            'attendance_percentage' => $attendancePercentage,
            'attendance_rating' => $attendanceRating,
            'period_locks' => $snapshots
                ->map(static fn (PayrollAttendanceSnapshot $snapshot): array => [
                    'id' => (int) $snapshot->attendance_period_lock_id,
                    'version' => (int) $snapshot->periodLock->version,
                    'lock_version' => (int) $snapshot->periodLock->lock_version,
                    'source_hash' => (string) $snapshot->periodLock->source_hash,
                ])
                ->unique('id')
                ->values()
                ->all(),
            'snapshots' => $snapshots->map(static fn (PayrollAttendanceSnapshot $snapshot): array => [
                'id' => (int) $snapshot->id,
                'period_start' => $snapshot->period_start->toDateString(),
                'period_end' => $snapshot->period_end->toDateString(),
                'source_hash' => (string) $snapshot->source_hash,
            ])->values()->all(),
        ];
        $evidence['evidence_hash'] = $this->hash($evidence);

        return ['rating' => $attendanceRating, 'evidence' => $evidence];
    }

    /** @return array{rating: float, evidence: array<string, mixed>} */
    private function legacyEvidence(PerformanceReview $review): array
    {
        if (! $review->legacy_manual_scoring || ! is_numeric(data_get($review->scoring_inputs, 'attendance'))) {
            throw ValidationException::withMessages([
                'scoring_inputs.attendance' => 'Finalized attendance evidence covering the complete review period is required before governed performance scoring.',
            ]);
        }

        $legacyScore = (float) data_get($review->scoring_inputs, 'attendance');
        $evidence = [
            'source_type' => 'legacy_manual_input',
            'review_id' => (int) $review->id,
            'attendance_rating' => $legacyScore,
            'legacy_manual_scoring' => true,
        ];
        $evidence['evidence_hash'] = $this->hash($evidence);

        return ['rating' => $legacyScore, 'evidence' => $evidence];
    }

    /** @param Collection<int, PayrollAttendanceSnapshot> $snapshots */
    private function coversReviewPeriod(Collection $snapshots, PerformanceReview $review): bool
    {
        if ($snapshots->isEmpty() || $review->period_start === null || $review->period_end === null) {
            return false;
        }

        $expected = CarbonImmutable::parse($review->period_start->toDateString());
        $reviewEnd = CarbonImmutable::parse($review->period_end->toDateString());

        foreach ($snapshots as $snapshot) {
            $start = CarbonImmutable::parse($snapshot->period_start->toDateString());
            $end = CarbonImmutable::parse($snapshot->period_end->toDateString());
            if (! $start->equalTo($expected) || $end->lessThan($start) || $end->greaterThan($reviewEnd)) {
                return false;
            }
            $expected = $end->addDay();
        }

        return $expected->equalTo($reviewEnd->addDay());
    }

    /** @param array<string, mixed> $evidence */
    private function hasValidHash(array $evidence): bool
    {
        $storedHash = $evidence['evidence_hash'] ?? null;

        return is_string($storedHash) && strlen($storedHash) === 64 && hash_equals($storedHash, $this->hash($evidence));
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function sameEvidence(array $left, array $right): bool
    {
        $leftHash = $left['evidence_hash'] ?? null;
        $rightHash = $right['evidence_hash'] ?? null;

        return is_string($leftHash)
            && is_string($rightHash)
            && hash_equals($leftHash, $rightHash)
            && $this->encodeCanonical($left) === $this->encodeCanonical($right);
    }

    /** @param array<string, mixed> $evidence */
    private function hash(array $evidence): string
    {
        unset($evidence['evidence_hash']);

        return hash('sha256', $this->encodeCanonical($evidence));
    }

    /** @param array<string, mixed> $value */
    private function encodeCanonical(array $value): string
    {
        return json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR);
    }

    private function throwStaleEvidence(): never
    {
        throw ValidationException::withMessages([
            'score_snapshot' => 'Finalized attendance evidence changed after calibration. Recalculate the governed score before closing this review.',
        ]);
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as &$child) {
            if (is_array($child)) {
                $child = $this->canonicalize($child);
            }
        }

        return $value;
    }
}
