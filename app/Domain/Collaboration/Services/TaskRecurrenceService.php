<?php

namespace App\Domain\Collaboration\Services;

use App\Models\WorkTask;
use App\Models\WorkTaskRecurrenceOccurrence;
use App\Models\WorkTaskRecurrenceRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TaskRecurrenceService
{
    public function synchronize(WorkTask $task): ?WorkTaskRecurrenceRule
    {
        $metadata = $task->metadata ?? [];
        $frequency = (string) ($metadata['recurrence_frequency'] ?? 'none');

        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true) || ! $task->due_at) {
            $task->recurrenceRule()->where('status', 'active')->update(['status' => 'cancelled', 'next_run_at' => null]);

            return null;
        }

        $interval = max(1, min(12, (int) ($metadata['recurrence_interval'] ?? 1)));
        $next = $this->nextDate($task->due_at, $frequency, $interval);
        $until = filled($metadata['recurrence_until'] ?? null)
            ? now()->parse((string) $metadata['recurrence_until'])->endOfDay()
            : null;

        return $task->recurrenceRule()->updateOrCreate([], [
            'company_id' => $task->company_id,
            'frequency' => $frequency,
            'interval' => $interval,
            'timezone' => (string) ($metadata['recurrence_timezone'] ?? config('app.timezone', 'Asia/Kolkata')),
            'next_run_at' => $until && $next->greaterThan($until) ? null : $next,
            'until_at' => $until,
            'status' => $until && $next->greaterThan($until) ? 'completed' : 'active',
            'metadata' => ['source' => 'task-metadata'],
        ]);
    }

    public function generateDue(CarbonInterface $now): int
    {
        $generated = 0;

        WorkTaskRecurrenceRule::query()
            ->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $ruleId) use ($now, &$generated): void {
                $generated += $this->generateRule($ruleId, $now);
            });

        return $generated;
    }

    public function generateNextForTask(WorkTask $task): int
    {
        $rule = $task->recurrenceRule()->first();
        return $rule?->next_run_at ? $this->generateRule($rule->id, $rule->next_run_at->copy()->addSecond()) : 0;
    }

    public function skipNext(WorkTaskRecurrenceRule $rule): WorkTaskRecurrenceRule
    {
        return DB::transaction(function () use ($rule): WorkTaskRecurrenceRule {
            $locked = WorkTaskRecurrenceRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active' || ! $locked->next_run_at) {
                return $locked;
            }

            WorkTaskRecurrenceOccurrence::query()->firstOrCreate(
                ['idempotency_key' => $this->occurrenceKey($locked, $locked->next_run_at)],
                [
                    'work_task_recurrence_rule_id' => $locked->id,
                    'source_work_task_id' => $locked->root_work_task_id,
                    'scheduled_for' => $locked->next_run_at,
                    'status' => 'skipped',
                    'metadata' => ['reason' => 'Skipped by an authorized user'],
                ],
            );

            $this->advance($locked, $locked->next_run_at);

            return $locked->fresh();
        });
    }

    public function cancel(WorkTaskRecurrenceRule $rule): WorkTaskRecurrenceRule
    {
        $rule->forceFill(['status' => 'cancelled', 'next_run_at' => null, 'lock_version' => $rule->lock_version + 1])->save();

        return $rule->fresh();
    }

    private function generateRule(int $ruleId, CarbonInterface $now): int
    {
        return DB::transaction(function () use ($ruleId, $now): int {
            $rule = WorkTaskRecurrenceRule::query()->with('rootTask.subtasks')->whereKey($ruleId)->lockForUpdate()->first();
            if (! $rule || $rule->status !== 'active' || ! $rule->next_run_at || $rule->next_run_at->greaterThan($now)) {
                return 0;
            }

            $scheduledFor = $rule->next_run_at->copy();
            $key = $this->occurrenceKey($rule, $scheduledFor);
            if (WorkTaskRecurrenceOccurrence::query()->where('idempotency_key', $key)->exists()) {
                $this->advance($rule, $scheduledFor);

                return 0;
            }

            $source = $rule->rootTask;
            if (! $source) {
                $rule->forceFill(['status' => 'cancelled', 'next_run_at' => null])->save();

                return 0;
            }

            $metadata = $source->metadata ?? [];
            unset($metadata['reminders_sent'], $metadata['recurrence_next_task_id']);
            $metadata['recurrence_rule_id'] = $rule->id;
            $metadata['recurrence_root_task_id'] = $source->id;

            $task = WorkTask::query()->create([
                'company_id' => $source->company_id,
                'project_id' => $source->project_id,
                'created_by_user_id' => $source->created_by_user_id,
                'assigned_to_user_id' => $source->assigned_to_user_id,
                'task_number' => $this->nextTaskNumber(),
                'client_token' => (string) Str::uuid(),
                'title' => $source->title,
                'description' => $source->description,
                'priority' => $source->priority,
                'status' => $source->assigned_to_user_id ? 'assigned' : 'draft',
                'due_at' => $scheduledFor,
                'module_context' => $source->module_context,
                'related_type' => $source->related_type,
                'related_id' => $source->related_id,
                'checklist' => collect($source->checklist ?? [])->map(fn (array $item): array => [...$item, 'done' => false])->all(),
                'workflow_history' => [[
                    'status' => 'recurrence_created',
                    'actor_user_id' => $source->created_by_user_id,
                    'actor' => 'Task automation',
                    'note' => 'Generated from recurring task '.$source->task_number,
                    'at' => now()->toISOString(),
                ]],
                'metadata' => $metadata,
            ]);

            foreach ($source->subtasks as $subtask) {
                $task->subtasks()->create([
                    'company_id' => $task->company_id,
                    'created_by_user_id' => $source->created_by_user_id,
                    'assigned_to_user_id' => $subtask->assigned_to_user_id,
                    'title' => $subtask->title,
                    'status' => 'open',
                    'priority' => $subtask->priority,
                    'metadata' => $subtask->metadata,
                ]);
            }

            WorkTaskRecurrenceOccurrence::query()->create([
                'work_task_recurrence_rule_id' => $rule->id,
                'source_work_task_id' => $source->id,
                'generated_work_task_id' => $task->id,
                'scheduled_for' => $scheduledFor,
                'status' => 'generated',
                'idempotency_key' => $key,
            ]);

            $sourceMetadata = $source->metadata ?? [];
            $sourceMetadata['recurrence_next_task_id'] = $task->id;
            $source->forceFill(['metadata' => $sourceMetadata])->save();

            $this->advance($rule, $scheduledFor, true);

            return 1;
        });
    }

    private function advance(WorkTaskRecurrenceRule $rule, CarbonInterface $from, bool $generated = false): void
    {
        $next = $this->nextDate($from, $rule->frequency, $rule->interval);
        $completed = $rule->until_at && $next->greaterThan($rule->until_at);
        $rule->forceFill([
            'next_run_at' => $completed ? null : $next,
            'status' => $completed ? 'completed' : 'active',
            'last_generated_at' => $generated ? now() : $rule->last_generated_at,
            'generation_count' => $generated ? $rule->generation_count + 1 : $rule->generation_count,
            'lock_version' => $rule->lock_version + 1,
        ])->save();
    }

    private function nextDate(CarbonInterface $date, string $frequency, int $interval): CarbonInterface
    {
        return match ($frequency) {
            'daily' => $date->copy()->addDays($interval),
            'weekly' => $date->copy()->addWeeks($interval),
            default => $date->copy()->addMonthsNoOverflow($interval),
        };
    }

    private function occurrenceKey(WorkTaskRecurrenceRule $rule, CarbonInterface $scheduledFor): string
    {
        return hash('sha256', $rule->id.'|'.$scheduledFor->utc()->format('Y-m-d H:i:s'));
    }

    private function nextTaskNumber(): string
    {
        return sprintf('TSKS-%05d', WorkTask::query()->withTrashed()->count() + 10001);
    }
}
