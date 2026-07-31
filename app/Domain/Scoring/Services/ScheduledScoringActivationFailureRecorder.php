<?php

namespace App\Domain\Scoring\Services;

use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Psr\Log\LoggerInterface;
use Throwable;

final class ScheduledScoringActivationFailureRecorder
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(
        ScoringRule $rule,
        ?User $actor,
        string $stage,
        string $classification,
    ): void {
        $context = $this->context($rule, $stage, $classification);

        try {
            $this->logger->warning('Scheduled scoring rule activation failed.', $context);
        } catch (Throwable) {
            // Logging must never prevent the scheduler from processing the next rule.
        }

        try {
            $this->audit->record(
                $actor,
                'scoring.rule.scheduled_activation_failed',
                'Failed scheduled scoring rule activation',
                $rule,
                $context,
            );
        } catch (Throwable) {
            try {
                $this->logger->error('Scheduled scoring rule activation audit could not be persisted.', [
                    'rule_id' => $rule->getKey(),
                    'rule_version' => $rule->version,
                    'failure_stage' => $stage,
                    'error_classification' => 'audit_persistence_failure',
                ]);
            } catch (Throwable) {
                // The original rule failure remains reflected in the command result.
            }
        }
    }

    /** @return array<string, mixed> */
    private function context(ScoringRule $rule, string $stage, string $classification): array
    {
        $currentStatus = (string) $rule->status;

        try {
            $currentStatus = (string) (ScoringRule::query()->whereKey($rule->getKey())->value('status') ?? $currentStatus);
        } catch (Throwable) {
            // Retain the already-loaded status when persistence is temporarily unavailable.
        }

        $schedulerRetryEligible = $currentStatus === 'scheduled';

        return [
            'rule_id' => $rule->getKey(),
            'rule_key' => $rule->rule_key,
            'rule_version' => $rule->version,
            'rule_status' => $currentStatus,
            'effective_at' => $rule->effective_at?->toIso8601String(),
            'failure_stage' => $stage,
            'error_classification' => $classification,
            'retry_context' => [
                'command' => 'scoring:activate-scheduled',
                'scheduler_retry_eligible' => $schedulerRetryEligible,
                'next_action' => $schedulerRetryEligible
                    ? 'retry_on_next_scheduler_run'
                    : 'manual_intervention_required',
            ],
        ];
    }
}
