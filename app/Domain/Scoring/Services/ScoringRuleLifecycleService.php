<?php

namespace App\Domain\Scoring\Services;

use App\Models\Company;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScoringRuleLifecycleService
{
    public function __construct(
        private readonly ScoringConfigurationValidator $validator,
        private readonly ScoringRuleIntegrityService $integrity,
        private readonly ScoringRuleEffectiveSlotGuard $effectiveSlots,
        private readonly AuditLogger $audit,
    ) {
    }

    public function validateRule(ScoringRule $rule, User $actor, ?Request $request = null): ScoringRule
    {
        return $this->transition(
            $rule,
            $actor,
            ['draft', 'rejected'],
            'validated',
            'scoring.rule.validated',
            function (ScoringRule $locked): array {
                $this->validator->validateForRule($locked->rule_key, $locked->configuration ?? []);

                return [];
            },
            $request,
        );
    }

    public function submit(ScoringRule $rule, User $actor, ?Request $request = null): ScoringRule
    {
        return $this->transition(
            $rule,
            $actor,
            ['validated'],
            'pending_approval',
            'scoring.rule.submitted',
            static fn (): array => ['submitted_at' => now()],
            $request,
        );
    }

    public function approve(ScoringRule $rule, User $actor, ?Request $request = null): ScoringRule
    {
        if ((int) $rule->created_by_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['rule' => 'The rule creator cannot approve the same version.']);
        }

        return $this->transition(
            $rule,
            $actor,
            ['pending_approval'],
            'approved',
            'scoring.rule.approved',
            static fn (): array => ['approved_by_user_id' => $actor->id, 'approved_at' => now()],
            $request,
        );
    }

    public function reject(ScoringRule $rule, string $reason, User $actor, ?Request $request = null): ScoringRule
    {
        return $this->transition(
            $rule,
            $actor,
            ['pending_approval'],
            'rejected',
            'scoring.rule.rejected',
            static fn (ScoringRule $locked): array => [
                'metadata' => array_merge($locked->metadata ?? [], [
                    'rejection_reason' => $reason,
                    'rejected_at' => now()->toIso8601String(),
                ]),
            ],
            $request,
        );
    }

    public function retire(ScoringRule $rule, string $reason, User $actor, ?Request $request = null): ScoringRule
    {
        $retired = $this->transition(
            $rule,
            $actor,
            ['active'],
            'retired',
            'scoring.rule.retired',
            static fn (ScoringRule $locked): array => [
                'retired_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], ['retirement_reason' => $reason]),
            ],
            $request,
        );
        Cache::forget("scoring.active.{$retired->company_id}.{$retired->rule_key}");

        return $retired;
    }

    public function activate(ScoringRule $rule, User $actor, ?Request $request = null): ScoringRule
    {
        return $this->withIntegrityFailureAudit(
            $rule,
            $actor,
            'scoring.rule.activate',
            $request,
            function () use ($rule, $actor, $request): ScoringRule {
                return DB::transaction(function () use ($rule, $actor, $request): ScoringRule {
                    $locked = ScoringRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($locked->status, ['approved', 'scheduled'], true)) {
                        throw ValidationException::withMessages(['rule' => 'Only approved or scheduled rules can be activated.']);
                    }

                    Company::query()->whereKey($locked->company_id)->lockForUpdate()->firstOrFail();
                    $this->integrity->assertUntampered($locked);
                    $this->validator->validateForRule($locked->rule_key, $locked->configuration ?? []);

                    $activationAt = now();
                    $effectiveAt = $locked->effective_at ?? $activationAt;
                    $this->effectiveSlots->assertAvailableAndOrdered($locked, $effectiveAt);

                    if ($effectiveAt->isFuture()) {
                        $locked->persistGovernedLifecycle(['status' => 'scheduled']);
                        $this->record($locked, $actor, 'scoring.rule.scheduled', $request);

                        return $locked->fresh(['createdBy', 'approvedBy', 'activatedBy']);
                    }

                    $activeVersions = ScoringRule::query()
                        ->where('company_id', $locked->company_id)
                        ->where('rule_key', $locked->rule_key)
                        ->where('status', 'active')
                        ->whereKeyNot($locked->id)
                        ->orderBy('version')
                        ->lockForUpdate()
                        ->get();

                    foreach ($activeVersions as $activeVersion) {
                        $activeVersion->persistGovernedLifecycle([
                            'status' => 'superseded',
                            'retired_at' => $activationAt,
                        ]);
                    }

                    $locked->persistGovernedLifecycle([
                        'status' => 'active',
                        'activated_by_user_id' => $actor->id,
                        'activated_at' => $activationAt,
                        'effective_at' => $effectiveAt,
                    ]);

                    Cache::forget("scoring.active.{$locked->company_id}.{$locked->rule_key}");
                    $this->record($locked, $actor, 'scoring.rule.activated', $request);

                    return $locked->fresh(['createdBy', 'approvedBy', 'activatedBy']);
                });
            },
        );
    }

    /**
     * @param list<string> $allowedStatuses
     * @param callable(ScoringRule): array<string, mixed> $beforeSave
     */
    private function transition(
        ScoringRule $rule,
        User $actor,
        array $allowedStatuses,
        string $status,
        string $event,
        callable $beforeSave,
        ?Request $request,
    ): ScoringRule {
        return $this->withIntegrityFailureAudit(
            $rule,
            $actor,
            $event,
            $request,
            function () use ($rule, $actor, $allowedStatuses, $status, $event, $beforeSave, $request): ScoringRule {
                return DB::transaction(function () use ($rule, $actor, $allowedStatuses, $status, $event, $beforeSave, $request): ScoringRule {
                    $locked = ScoringRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($locked->status, $allowedStatuses, true)) {
                        throw ValidationException::withMessages(['rule' => 'The scoring rule is not in a valid state for this action.']);
                    }

                    $this->integrity->assertUntampered($locked);
                    $changes = $beforeSave($locked);
                    $changes['status'] = $status;
                    $locked->persistGovernedLifecycle($changes);
                    $this->record($locked, $actor, $event, $request);

                    return $locked->fresh(['createdBy', 'approvedBy', 'activatedBy']);
                });
            },
        );
    }

    /** @param callable(): ScoringRule $operation */
    private function withIntegrityFailureAudit(
        ScoringRule $rule,
        User $actor,
        string $attemptedEvent,
        ?Request $request,
        callable $operation,
    ): ScoringRule {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            if (! array_key_exists(ScoringRuleIntegrityService::ERROR_KEY, $exception->errors())) {
                throw $exception;
            }

            $failedRule = ScoringRule::query()->find($rule->id) ?? $rule;
            $this->audit->record($actor, 'scoring.rule.integrity_failed', 'Blocked scoring rule lifecycle action after integrity failure', $failedRule, [
                'rule_key' => $failedRule->rule_key,
                'version' => $failedRule->version,
                'status' => $failedRule->status,
                'attempted_event' => $attemptedEvent,
                'stored_checksum' => $failedRule->configuration_checksum,
                'expected_checksum' => $this->integrity->expectedChecksum($failedRule),
            ], $request);

            throw $exception;
        }
    }

    private function record(ScoringRule $rule, User $actor, string $event, ?Request $request): void
    {
        $this->audit->record($actor, $event, str($event)->afterLast('.')->replace('_', ' ')->headline()->toString().' scoring rule', $rule, [
            'rule_key' => $rule->rule_key,
            'version' => $rule->version,
            'status' => $rule->status,
        ], $request);
    }
}
