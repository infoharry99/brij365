<?php

namespace App\Events\Calendar;

use App\Models\CalendarEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CalendarEventChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CalendarEvent $event, public string $change = 'updated') {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('calendar.company.'.$this->event->company_id)];
        $ids = collect([$this->event->organizer_user_id])->merge(collect($this->event->attendees ?? [])->pluck('user_id'))->filter()->unique();
        foreach ($ids as $id) $channels[] = new PrivateChannel('calendar.user.'.$id);
        return $channels;
    }

    public function broadcastAs(): string { return 'calendar.changed'; }

    public function broadcastWith(): array
    {
        return ['event_id' => $this->event->id, 'event_number' => $this->event->event_number, 'change' => $this->change, 'lock_version' => $this->event->lock_version, 'updated_at' => $this->event->updated_at?->toISOString()];
    }
}
