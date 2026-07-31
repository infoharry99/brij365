<?php

namespace App\Domain\Collaboration\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\CalendarEventRecurrenceRule;
use App\Models\CalendarEventReminderDelivery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CalendarLifecycleManager
{
    public function instant(mixed $value, string $timezone): Carbon
    {
        $raw = trim((string) $value);
        $hasOffset = preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $raw) === 1;

        return ($hasOffset ? Carbon::parse($raw) : Carbon::parse($raw, $timezone))->utc();
    }

    public function assertVersion(CalendarEvent $event, mixed $submitted): void
    {
        if ($submitted !== null && (int) $submitted !== (int) $event->lock_version) {
            throw ValidationException::withMessages(['lock_version' => 'This event was updated by someone else. Refresh the calendar before saving your changes.']);
        }
    }

    /** @param array<string,mixed> $data */
    public function synchronize(CalendarEvent $event, array $data, User $actor): CalendarEvent
    {
        $this->syncAttendees($event, $data, $actor);
        $this->syncRecurrence($event, $data);
        $this->syncReminders($event, $data);

        return $event->load(['attendeeRecords.user', 'recurrenceRule', 'reminderDeliveries', 'attachments.uploadedBy']);
    }

    public function cancelPendingReminders(CalendarEvent $event): void
    {
        $event->reminderDeliveries()->where('status', 'pending')->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $data */
    private function syncAttendees(CalendarEvent $event, array $data, User $actor): void
    {
        $internalUserIds = collect($event->attendees ?? [])->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
        $usersById = User::query()->whereIn('id', $internalUserIds)->get()->keyBy('id');

        $internal = collect($event->attendees ?? [])
            ->filter(fn (array $row): bool => ! empty($row['user_id']))
            ->map(function (array $row) use ($usersById): array {
                $userId = (int) $row['user_id'];
                $user = $usersById->get($userId);

                return [
                    'user_id' => $userId,
                    'name' => (string) ($row['name'] ?? $user?->name ?? 'Participant'),
                    'email' => strtolower((string) ($row['email'] ?? $user?->email ?? '')),
                    'response' => (string) ($row['response'] ?? 'pending'),
                    'attendee_type' => 'internal',
                ];
            });

        $guests = collect($data['guests'] ?? [])->map(fn (array $row): array => [
            'user_id' => null,
            'name' => trim((string) ($row['name'] ?? $row['email'] ?? 'Guest')),
            'email' => strtolower(trim((string) ($row['email'] ?? ''))),
            'response' => 'pending',
            'attendee_type' => 'guest',
        ]);

        $rows = $internal->merge($guests)->filter(fn (array $row): bool => filter_var($row['email'], FILTER_VALIDATE_EMAIL) !== false)->unique('email')->values();
        $keep = [];
        foreach ($rows as $row) {
            $existing = $event->attendeeRecords()->where('email', $row['email'])->first();
            $response = $existing?->response ?? $row['response'];
            if ((int) ($row['user_id'] ?? 0) === (int) $event->organizer_user_id) {
                $response = 'accepted';
            }
            $record = $event->attendeeRecords()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'user_id' => $row['user_id'], 'name' => $row['name'], 'attendee_type' => $row['attendee_type'],
                    'response' => $response, 'invited_at' => $existing?->invited_at ?? now(),
                    'guest_token_hash' => $row['attendee_type'] === 'guest' ? ($existing?->guest_token_hash ?? hash('sha256', Str::random(64))) : null,
                ],
            );
            $keep[] = $record->id;
        }
        $event->attendeeRecords()->whereNotIn('id', $keep ?: [0])->delete();

        $legacy = $event->attendeeRecords()->get()->map(fn (CalendarEventAttendee $row): array => [
            'user_id' => $row->user_id, 'name' => $row->name, 'email' => $row->email,
            'response' => $row->response, 'attendee_type' => $row->attendee_type,
        ])->all();
        $event->forceFill(['attendees' => $legacy])->saveQuietly();
    }

    /** @param array<string,mixed> $data */
    private function syncRecurrence(CalendarEvent $event, array $data): void
    {
        $frequency = (string) data_get($data, 'recurrence.frequency', data_get($data, 'metadata.recurrence', 'none'));
        if ($frequency === '' || $frequency === 'none') {
            $event->recurrenceRule()->where('status', 'active')->update(['status' => 'cancelled', 'next_run_at' => null, 'updated_at' => now()]);
            return;
        }
        $localStart = CarbonImmutable::instance($event->starts_at)->setTimezone($event->timezone);
        $interval = max(1, min(52, (int) data_get($data, 'recurrence.interval', 1)));
        $next = match ($frequency) {
            'daily' => $localStart->addDays($interval),
            'weekly' => $localStart->addWeeks($interval),
            'monthly' => $localStart->addMonthsNoOverflow($interval),
            'yearly' => $localStart->addYears($interval),
            default => null,
        };
        if (! $next) return;

        $until = data_get($data, 'recurrence.until_at');
        $untilAt = $until ? CarbonImmutable::parse((string) $until, $event->timezone)->endOfDay()->utc() : null;

        CalendarEventRecurrenceRule::query()->updateOrCreate(['root_event_id' => $event->id], [
            'company_id' => $event->company_id, 'frequency' => $frequency, 'interval' => $interval,
            'weekdays' => data_get($data, 'recurrence.weekdays'), 'month_day' => data_get($data, 'recurrence.month_day'),
            'timezone' => $event->timezone, 'occurrence_limit' => data_get($data, 'recurrence.occurrence_limit'),
            'until_at' => $untilAt, 'next_run_at' => $next->utc(), 'status' => 'active',
            'lock_version' => ((int) ($event->recurrenceRule?->lock_version ?? 0)) + 1,
        ]);
    }

    /** @param array<string,mixed> $data */
    private function syncReminders(CalendarEvent $event, array $data): void
    {
        $minutes = collect($data['reminders'] ?? $event->reminders ?? [['minutes_before' => 30]])
            ->pluck('minutes_before')->map(fn ($value): int => max(0, min(43200, (int) $value)))->unique()->values();
        $event->reminderDeliveries()->where('status', 'pending')->delete();
        if (! in_array($event->status, ['scheduled', 'rescheduled'], true)) return;

        $recipients = $event->attendeeRecords()->get();
        foreach ($recipients as $attendee) {
            foreach ($minutes as $before) {
                $scheduled = CarbonImmutable::instance($event->starts_at)->subMinutes($before);
                if ($scheduled->isPast()) continue;
                CalendarEventReminderDelivery::query()->firstOrCreate([
                    'idempotency_key' => implode(':', ['calendar', $event->id, $attendee->id, 'in_app', $before, $event->starts_at->timestamp]),
                ], [
                    'calendar_event_id' => $event->id, 'calendar_event_attendee_id' => $attendee->id,
                    'channel' => $attendee->attendee_type === 'guest' ? 'email' : 'in_app', 'minutes_before' => $before,
                    'scheduled_for' => $scheduled, 'status' => 'pending',
                ]);
            }
        }
    }
}
