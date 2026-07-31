<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\ScoreSnapshotRowData;
use App\Application\Scoring\DTOs\ScoringRuleRowData;
use App\Models\ScoreSnapshot;
use App\Models\ScoringRecalculationRun;
use App\Models\ScoringRecalculationFailure;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

final class ScoringRuleRegister
{
    private const VIEW_RULE_KEYS = [
        'lead' => 'lead_quality', 'performance' => 'employee_performance', 'confirmation' => 'employee_confirmation',
        'recruitment' => 'recruitment_interview', 'vendor' => 'vendor_performance', 'project' => 'project_health',
        'customer-satisfaction' => 'customer_satisfaction', 'exit-feedback' => 'exit_feedback',
    ];

    private const BUSINESS_RULE_KEYS = [
        'lead_quality', 'employee_confirmation', 'recruitment_interview', 'vendor_performance',
        'project_health', 'customer_satisfaction', 'exit_feedback',
    ];

    private const ALL_RULE_KEYS = [
        'lead_quality', 'employee_performance', 'employee_confirmation', 'recruitment_interview',
        'vendor_performance', 'project_health', 'customer_satisfaction', 'exit_feedback',
    ];

    private const NON_SCORING_RULE_VIEWS = ['statutory', 'roster', 'simulation'];

    public function __construct(private readonly CompanyScopeService $companyScope)
    {
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function forUser(User $user, array $filters): array
    {
        if (! $user->can('viewAny', ScoringRule::class)) {
            return [
                'rules' => [],
                'snapshots' => [],
                'runs' => [],
                'failures' => [],
                'counts' => ['rules' => 0, 'active' => 0, 'pending' => 0, 'snapshots' => 0],
            ];
        }

        $view = (string) ($filters['view'] ?? 'overview');
        $permittedRuleKeys = $this->permittedRuleKeys($user);
        $visibleRuleKeys = $this->visibleRuleKeysForView($view, $permittedRuleKeys);
        $ruleQuery = $this->companyScope->apply(
            ScoringRule::query()->with('createdBy:id,name'),
            $user,
        )->whereIn('rule_key', $visibleRuleKeys);

        $ruleQuery
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['q']), function ($query) use ($filters): void {
                $pattern = '%'.addcslashes(trim((string) $filters['q']), '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $pattern)->orWhere('rule_key', 'like', $pattern));
            });

        $rules = $ruleQuery->orderBy('rule_key')->latest('version')->limit(100)->get()->map(
            fn (ScoringRule $rule): ScoringRuleRowData => new ScoringRuleRowData(
                id: $rule->id,
                ruleKey: $rule->rule_key,
                name: $rule->name,
                version: $rule->version,
                status: str($rule->status)->headline()->toString(),
                effectiveAt: $rule->effective_at?->format('d M Y, h:i A') ?? 'Not scheduled',
                createdBy: $rule->createdBy?->name ?? 'Unknown',
                checksum: substr($rule->configuration_checksum, 0, 12),
                canUpdate: $user->can('update', $rule),
                canClone: $user->can('clone', $rule),
                canReject: $user->can('reject', $rule),
                canRetire: $user->can('retire', $rule),
                canRecalculate: $user->can('recalculate', $rule),
                canValidate: $user->can('validate', $rule),
                canSubmit: $user->can('submit', $rule),
                canApprove: $user->can('approve', $rule),
                canActivate: $user->can('activate', $rule),
            ),
        )->all();

        $snapshotQuery = $this->companyScope->apply(
            ScoreSnapshot::query()->with('scoringRule:id,name,rule_key,configuration'),
            $user,
        )->whereHas('scoringRule', fn ($query) => $query->whereIn('rule_key', $visibleRuleKeys));

        $snapshots = $snapshotQuery->latest('calculated_at')->limit(20)->get()->map(
            static fn (ScoreSnapshot $snapshot): ScoreSnapshotRowData => new ScoreSnapshotRowData(
                id: $snapshot->id,
                subject: class_basename($snapshot->subject_type).' #'.$snapshot->subject_id,
                ruleName: $snapshot->scoringRule?->name ?? 'Scoring Rule',
                score: number_format((float) $snapshot->total_score, 2),
                band: $snapshot->score_band ?? 'Unclassified',
                ruleVersion: $snapshot->rule_version,
                calculatedAt: $snapshot->calculated_at?->format('d M Y, h:i A') ?? 'Unavailable',
                override: $snapshot->is_override,
                canOverride: $user->can('override', $snapshot),
            ),
        )->all();

        $runQuery = $this->companyScope->apply(
            ScoringRecalculationRun::query()->with('scoringRule:id,name'),
            $user,
        )->whereHas('scoringRule', fn ($query) => $query->whereIn('rule_key', $visibleRuleKeys));
        $companyId = $this->companyScope->companyIdFor($user);
        $failures = ScoringRecalculationFailure::query()->with('run.scoringRule:id,name')
            ->when($companyId !== null, fn ($query) => $query->whereHas('run', fn ($run) => $run->where('company_id', $companyId)))
            ->whereHas('run.scoringRule', fn ($query) => $query->whereIn('rule_key', $visibleRuleKeys))
            ->latest()->limit(20)->get()->map(static fn (ScoringRecalculationFailure $failure): array => [
                'id' => $failure->id,
                'rule' => $failure->run?->scoringRule?->name ?? 'Scoring Rule',
                'subject' => class_basename($failure->subject_type).' #'.$failure->subject_id,
                'message' => $failure->error_message,
            ])->all();

        return [
            'rules' => $rules,
            'snapshots' => $snapshots,
            'runs' => $runQuery->latest()->limit(10)->get()->map(static fn (ScoringRecalculationRun $run): array => [
                'id' => $run->id,
                'rule' => $run->scoringRule?->name ?? 'Scoring Rule',
                'status' => str($run->status)->headline()->toString(),
                'processed' => $run->processed_records,
                'total' => $run->total_records,
                'failed' => $run->failed_records,
            ])->all(),
            'failures' => $failures,
            'counts' => [
                'rules' => $this->companyScope->apply(ScoringRule::query(), $user)->whereIn('rule_key', $visibleRuleKeys)->count(),
                'active' => $this->companyScope->apply(ScoringRule::query(), $user)->whereIn('rule_key', $visibleRuleKeys)->where('status', 'active')->count(),
                'pending' => $this->companyScope->apply(ScoringRule::query(), $user)->whereIn('rule_key', $visibleRuleKeys)->where('status', 'pending_approval')->count(),
                'snapshots' => $this->companyScope->apply(ScoreSnapshot::query(), $user)
                    ->whereHas('scoringRule', fn ($query) => $query->whereIn('rule_key', $visibleRuleKeys))
                    ->count(),
            ],
        ];
    }

    /** @return list<string> */
    private function permittedRuleKeys(User $user): array
    {
        return collect(self::ALL_RULE_KEYS)
            ->filter(fn (string $ruleKey): bool => $user->can('viewForKey', [ScoringRule::class, $ruleKey]))
            ->values()
            ->all();
    }

    /** @param list<string> $permittedRuleKeys @return list<string> */
    private function visibleRuleKeysForView(string $view, array $permittedRuleKeys): array
    {
        $requestedRuleKeys = match (true) {
            isset(self::VIEW_RULE_KEYS[$view]) => [self::VIEW_RULE_KEYS[$view]],
            $view === 'business' => self::BUSINESS_RULE_KEYS,
            in_array($view, self::NON_SCORING_RULE_VIEWS, true) => [],
            in_array($view, ['overview', 'audit', 'score-history', 'rule-history'], true) => self::ALL_RULE_KEYS,
            default => [],
        };

        return array_values(array_intersect($requestedRuleKeys, $permittedRuleKeys));
    }
}
