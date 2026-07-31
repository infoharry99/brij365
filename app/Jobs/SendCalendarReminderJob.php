<?php

namespace App\Jobs;

use App\Models\CalendarEventReminderDelivery;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class SendCalendarReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(NotificationCenterService $notifications): void
    {
        DB::transaction(function () use ($notifications): void {
            $delivery = CalendarEventReminderDelivery::query()->with(['event', 'attendee.user'])->lockForUpdate()->find($this->deliveryId);
            if (! $delivery || $delivery->status === 'sent' || ! $delivery->event || ! $delivery->attendee || ! in_array($delivery->event->status, ['scheduled','rescheduled'], true)) return;
            $delivery->forceFill(['status' => 'processing', 'attempt_count' => $delivery->attempt_count + 1, 'last_error_code' => null])->save();
            try {
                if ($delivery->attendee->user) {
                    $notifications->sendToUser($delivery->attendee->user, [
                        'category' => 'calendar', 'severity' => 'info', 'title' => 'Upcoming event: '.$delivery->event->title,
                        'body' => $delivery->event->starts_at->setTimezone($delivery->event->timezone)->format('D, d M Y g:i A'),
                        'action_url' => route('collaboration.calendar-events.index', ['event_id' => $delivery->event->id], false),
                        'payload' => ['calendar_event_id' => $delivery->event->id],
                    ], $delivery->event->organizer, $delivery->event);
                } else {
                    Mail::raw('Reminder: '.$delivery->event->title.' at '.$delivery->event->starts_at->setTimezone($delivery->event->timezone)->format('D, d M Y g:i A T'), fn ($mail) => $mail->to($delivery->attendee->email)->subject('Calendar reminder: '.$delivery->event->title));
                }
                $delivery->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
            } catch (\Throwable $exception) {
                $delivery->forceFill(['status' => $delivery->attempt_count >= 3 ? 'failed' : 'pending', 'last_error_code' => class_basename($exception)])->save();
                throw $exception;
            }
        });
    }
}
