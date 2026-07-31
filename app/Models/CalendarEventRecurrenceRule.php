<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventRecurrenceRule extends Model
{
    protected $fillable = ['company_id', 'root_event_id', 'frequency', 'interval', 'weekdays', 'month_day', 'timezone', 'occurrence_limit', 'until_at', 'next_run_at', 'last_generated_at', 'generated_count', 'status', 'lock_version', 'metadata'];

    protected function casts(): array
    {
        return ['weekdays' => 'array', 'until_at' => 'datetime', 'next_run_at' => 'datetime', 'last_generated_at' => 'datetime', 'metadata' => 'array'];
    }

    public function rootEvent(): BelongsTo { return $this->belongsTo(CalendarEvent::class, 'root_event_id'); }
}
