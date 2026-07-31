<?php

namespace App\Console\Commands;

use App\Domain\Scoring\Services\ScoringRecalculationService;
use App\Domain\Scoring\Services\ScoringRuleLifecycleService;
use App\Domain\Scoring\Services\ScheduledScoringActivationFailureRecorder;
use App\Models\ScoringRule;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ActivateScheduledScoringRules extends Command
{
    protected $signature = 'scoring:activate-scheduled {--json : Output machine-readable results}';
    protected $description = 'Activate approved scoring rules whose effective time has arrived';

    public function handle(
        ScoringRuleLifecycleService $lifecycle,
        ScoringRecalculationService $recalculation,
        ScheduledScoringActivationFailureRecorder $failureRecorder,
    ): int {
        $activated = 0;
        $failed = 0;
        ScoringRule::query()->with(['approvedBy', 'createdBy'])->where('status', 'scheduled')
            ->whereNotNull('effective_at')->where('effective_at', '<=', now())
            ->orderBy('effective_at')->orderBy('version')->orderBy('id')->each(
                function (ScoringRule $rule) use ($lifecycle, $recalculation, $failureRecorder, &$activated, &$failed): void {
                    $actor = $rule->approvedBy;
                    if (! $actor || (int) $actor->id === (int) $rule->created_by_user_id) {
                        $failed++;
                        $failureRecorder->record(
                            $rule,
                            $actor,
                            'governance_actor_resolution',
                            $actor ? 'maker_checker_violation' : 'approver_missing',
                        );

                        return;
                    }

                    $stage = 'activation';
                    try {
                        $active = $lifecycle->activate($rule, $actor);
                        $stage = 'recalculation';
                        $recalculation->start($active, $actor);
                        $activated++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $failureRecorder->record(
                            $rule,
                            $actor,
                            $stage,
                            $this->classifyFailure($exception),
                        );
                    }
                },
            );
        $payload = ['status' => $failed === 0 ? 'ok' : 'completed_with_failures', 'activated' => $activated, 'failed' => $failed, 'checked_at' => now()->toIso8601String()];
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info("Activated {$activated} scheduled scoring rule(s); {$failed} failed.");
        }
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function classifyFailure(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ValidationException => 'validation_error',
            $exception instanceof QueryException => 'persistence_error',
            default => 'unexpected_error',
        };
    }
}
