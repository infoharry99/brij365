<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventAttendee extends Model
{
    protected $fillable = ['calendar_event_id', 'user_id', 'name', 'email', 'attendee_type', 'response', 'responded_at', 'guest_token_hash', 'invited_at', 'last_notified_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'invited_at' => 'datetime', 'last_notified_at' => 'datetime'];
    }

    public function event(): BelongsTo { return $this->belongsTo(CalendarEvent::class, 'calendar_event_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
