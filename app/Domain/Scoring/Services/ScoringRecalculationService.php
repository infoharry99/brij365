<?php

namespace App\Domain\Scoring\Services;

use App\Jobs\Scoring\ProcessScoringRecalculation;
use App\Models\ScoringRecalculationRun;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScoringRecalculationService
{
    public function __construct(private readonly ScoringSubjectRegistry $subjects, private readonly AuditLogger $audit) {}

    public function start(ScoringRule $rule, User $actor, ?Request $request = null): ScoringRecalculationRun
    {
        if ($rule->status !== 'active') {
            throw ValidationException::withMessages(['rule' => 'Only the active scoring rule can recalculate records.']);
        }

        return DB::transaction(function () use ($rule, $actor, $request): ScoringRecalculationRun {
            $existing = ScoringRecalculationRun::query()->where('scoring_rule_id', $rule->id)->whereIn('status', ['pending', 'running'])->lockForUpdate()->first();
            if ($existing) {
                throw ValidationException::withMessages(['recalculation' => 'A recalculation run is already pending or in progress.']);
            }
            $run = ScoringRecalculationRun::create([
                'company_id' => $rule->company_id, 'scoring_rule_id' => $rule->id,
                'triggered_by_user_id' => $actor->id, 'status' => 'pending',
                'total_records' => $this->subjects->eligibleQuery($rule)->count(),
                'metadata' => ['rule_key' => $rule->rule_key, 'rule_version' => $rule->version],
            ]);
            $this->audit->record($actor, 'scoring.recalculation.started', 'Started scoring recalculation', $run, [
                'rule_id' => $rule->id, 'total_records' => $run->total_records,
            ], $request);
            DB::afterCommit(static fn () => ProcessScoringRecalculation::dispatch($run->id));
            return $run;
        });
    }
}
