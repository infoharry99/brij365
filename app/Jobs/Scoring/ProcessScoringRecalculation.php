<?php

namespace App\Jobs\Scoring;

use App\Application\Scoring\DTOs\ScoreCalculationResultData;
use App\Domain\Scoring\Services\ScoreSnapshotWriter;
use App\Domain\Scoring\Services\ScoringSubjectRegistry;
use App\Domain\Scoring\Services\StructuredScoreCalculator;
use App\Models\ScoreSnapshot;
use App\Models\ScoringRecalculationFailure;
use App\Models\ScoringRecalculationRun;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProcessScoringRecalculation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public readonly int $runId) {}

    public function handle(
        ScoringSubjectRegistry $subjects,
        StructuredScoreCalculator $calculator,
        ScoreSnapshotWriter $snapshots,
        NotificationCenterService $notifications,
    ): void {
        $run = DB::transaction(function (): ?ScoringRecalculationRun {
            $lockedRun = ScoringRecalculationRun::query()->lockForUpdate()->findOrFail($this->runId);
            if (! in_array($lockedRun->status, ['pending', 'running'], true)) {
                return null;
            }

            $lockedRun->forceFill([
                'status' => 'running',
                'started_at' => $lockedRun->started_at ?? now(),
            ])->save();

            return $lockedRun->load(['scoringRule', 'triggeredBy']);
        });

        if ($run === null) {
            return;
        }

        $subjects->eligibleQuery($run->scoringRule)->orderBy('id')->chunkById(100, function ($records) use ($run, $subjects, $calculator, $snapshots): void {
            foreach ($records as $record) {
                try {
                    $subject = $subjects->subject($run->scoringRule, $record);
                    $result = $calculator->calculate($run->scoringRule, $subject->inputs);
                    $this->writeSnapshotOnce(
                        $run,
                        $snapshots,
                        $result,
                        $subject->type,
                        $subject->id,
                        $subject->inputs,
                        $subject->metadata,
                    );
                } catch (ValidationException $exception) {
                    $this->recordValidationFailureOnce($run, $record);
                }
            }
        });

        $completedRun = DB::transaction(function () use ($run): ?ScoringRecalculationRun {
            $lockedRun = ScoringRecalculationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($lockedRun->status === 'completed') {
                return null;
            }
            if (! in_array($lockedRun->status, ['pending', 'running'], true)) {
                return null;
            }

            $lockedRun->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

            return $lockedRun->load(['scoringRule', 'triggeredBy']);
        });

        if ($completedRun === null) {
            return;
        }

        $notifications->sendToPermission(['scoring.manage', 'scoring.recalculate'], [
            'category' => 'system', 'severity' => $completedRun->failed_records > 0 ? 'warning' : 'success',
            'title' => 'Scoring recalculation completed',
            'body' => "{$completedRun->scoringRule->name} processed {$completedRun->processed_records} record(s) with {$completedRun->failed_records} failure(s).",
            'action_url' => route('scoring.index', ['view' => 'score-history'], false),
            'payload' => ['run_id' => $completedRun->id, 'rule_id' => $completedRun->scoring_rule_id],
        ], $completedRun->triggeredBy, $completedRun, $completedRun->company_id);
    }

    /** @param array<string, mixed> $inputs @param array<string, mixed> $metadata */
    private function writeSnapshotOnce(
        ScoringRecalculationRun $run,
        ScoreSnapshotWriter $snapshots,
        ScoreCalculationResultData $result,
        string $subjectType,
        int $subjectId,
        array $inputs,
        array $metadata,
    ): void {
        DB::transaction(function () use ($run, $snapshots, $result, $subjectType, $subjectId, $inputs, $metadata): void {
            $lockedRun = ScoringRecalculationRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! in_array($lockedRun->status, ['pending', 'running'], true)
                || $this->outcomeExists($lockedRun->id, $run->scoring_rule_id, $subjectType, $subjectId)) {
                return;
            }

            $snapshots->write(
                $run->scoringRule,
                $result,
                $subjectType,
                $subjectId,
                $inputs,
                array_merge($metadata, ['recalculation_run_id' => $lockedRun->id]),
            );

            $lockedRun->forceFill(['processed_records' => $lockedRun->processed_records + 1])->save();
        });
    }

    private function recordValidationFailureOnce(ScoringRecalculationRun $run, Model $record): void
    {
        DB::transaction(function () use ($run, $record): void {
            $lockedRun = ScoringRecalculationRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! in_array($lockedRun->status, ['pending', 'running'], true)
                || $this->outcomeExists($lockedRun->id, $run->scoring_rule_id, $record::class, (int) $record->getKey())) {
                return;
            }

            ScoringRecalculationFailure::create([
                'scoring_recalculation_run_id' => $lockedRun->id,
                'subject_type' => $record::class,
                'subject_id' => $record->getKey(),
                'error_code' => 'source_evidence_unavailable',
                'error_message' => 'The record could not be recalculated because required scoring evidence is incomplete.',
                'context' => ['rule_key' => $run->scoringRule->rule_key],
            ]);
            $lockedRun->forceFill(['failed_records' => $lockedRun->failed_records + 1])->save();
        });
    }

    private function outcomeExists(int $runId, int $ruleId, string $subjectType, int $subjectId): bool
    {
        return ScoreSnapshot::query()
            ->where('scoring_rule_id', $ruleId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('metadata->recalculation_run_id', $runId)
            ->exists()
            || ScoringRecalculationFailure::query()
                ->where('scoring_recalculation_run_id', $runId)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->exists();
    }

    public function failed(Throwable $exception): void
    {
        ScoringRecalculationRun::query()->whereKey($this->runId)->update(['status' => 'failed', 'completed_at' => now(), 'updated_at' => now()]);
    }
}
