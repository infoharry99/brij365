<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventAttachment extends Model
{
    protected $fillable = ['calendar_event_id', 'uploaded_by_user_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'checksum_sha256', 'scan_status'];
    public function event(): BelongsTo { return $this->belongsTo(CalendarEvent::class, 'calendar_event_id'); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }
}
