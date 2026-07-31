<?php

namespace App\Domain\Collaboration\Services;

use App\Events\Calendar\CalendarEventChanged;
use App\Models\CalendarEvent;
use App\Models\CalendarEventRecurrenceRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class CalendarRecurrenceGenerator
{
    public function __construct(private readonly CalendarLifecycleManager $lifecycle) {}

    public function generateDue(CarbonInterface $now): int
    {
        $generated = 0;
        $ids = CalendarEventRecurrenceRule::query()->where('status', 'active')->whereNotNull('next_run_at')->where('next_run_at', '<=', $now)
            ->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            $generated += $this->generateOne((int) $id, $now);
        }
        return $generated;
    }

    private function generateOne(int $ruleId, CarbonInterface $now): int
    {
        return DB::transaction(function () use ($ruleId, $now): int {
            $rule = CalendarEventRecurrenceRule::query()->with('rootEvent')->lockForUpdate()->find($ruleId);
            if (! $rule || $rule->status !== 'active' || ! $rule->next_run_at || $rule->next_run_at->gt($now) || ! $rule->rootEvent) return 0;
            if (($rule->occurrence_limit && $rule->generated_count >= $rule->occurrence_limit) || ($rule->until_at && $rule->next_run_at->gt($rule->until_at))) {
                $rule->update(['status' => 'completed', 'next_run_at' => null]);
                return 0;
            }

            $root = $rule->rootEvent;
            $start = CarbonImmutable::instance($rule->next_run_at)->utc();
            $duration = max(1, CarbonImmutable::instance($root->starts_at)->diffInMinutes(CarbonImmutable::instance($root->ends_at)));
            $key = 'calendar-series:'.$rule->id.':'.$start->format('YmdHis');
            $event = CalendarEvent::query()->firstOrCreate(['occurrence_key' => $key], [
                'company_id' => $root->company_id, 'project_id' => $root->project_id, 'organizer_user_id' => $root->organizer_user_id,
                'event_number' => 'CAL-'.str_pad((string) ((CalendarEvent::withTrashed()->max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT),
                'title' => $root->title, 'description' => $root->description, 'event_type' => $root->event_type, 'status' => 'scheduled',
                'starts_at' => $start, 'ends_at' => $start->addMinutes($duration), 'timezone' => $root->timezone,
                'location' => $root->location, 'meeting_url' => $root->meeting_url, 'visibility' => $root->visibility,
                'attendees' => $root->attendees, 'reminders' => $root->reminders, 'related_type' => $root->related_type,
                'related_id' => $root->related_id, 'workflow_history' => [['action' => 'generated', 'at' => now()->toISOString()]],
                'metadata' => array_merge($root->metadata ?? [], ['recurrence' => $rule->frequency]), 'series_root_id' => $root->id,
            ]);
            if ($event->wasRecentlyCreated) {
                $this->lifecycle->synchronize($event, ['attendees' => $root->attendees, 'reminders' => $root->reminders, 'recurrence' => ['frequency' => 'none']], $root->organizer);
                CalendarEventChanged::dispatch($event, 'occurrence_generated');
            }

            $rule->forceFill([
                'generated_count' => $rule->generated_count + ($event->wasRecentlyCreated ? 1 : 0),
                'last_generated_at' => $start,
                'next_run_at' => $this->next($start->setTimezone($rule->timezone), $rule)->utc(),
                'lock_version' => $rule->lock_version + 1,
            ])->save();
            return $event->wasRecentlyCreated ? 1 : 0;
        });
    }

    private function next(CarbonImmutable $start, CalendarEventRecurrenceRule $rule): CarbonImmutable
    {
        return match ($rule->frequency) {
            'daily' => $start->addDays($rule->interval), 'weekly' => $start->addWeeks($rule->interval),
            'monthly' => $start->addMonthsNoOverflow($rule->interval), 'yearly' => $start->addYears($rule->interval),
            default => $start->addYear(),
        };
    }
}
