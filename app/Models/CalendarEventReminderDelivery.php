<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventReminderDelivery extends Model
{
    protected $fillable = ['calendar_event_id', 'calendar_event_attendee_id', 'channel', 'minutes_before', 'scheduled_for', 'status', 'attempt_count', 'idempotency_key', 'sent_at', 'last_error_code'];

    protected function casts(): array { return ['scheduled_for' => 'datetime', 'sent_at' => 'datetime']; }
    public function event(): BelongsTo { return $this->belongsTo(CalendarEvent::class, 'calendar_event_id'); }
    public function attendee(): BelongsTo { return $this->belongsTo(CalendarEventAttendee::class, 'calendar_event_attendee_id'); }
}
