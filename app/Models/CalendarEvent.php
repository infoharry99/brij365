<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'organizer_user_id',
        'event_number',
        'title',
        'description',
        'event_type',
        'status',
        'starts_at',
        'ends_at',
        'timezone',
        'location',
        'meeting_url',
        'visibility',
        'attendees',
        'reminders',
        'related_type',
        'related_id',
        'workflow_history',
        'metadata',
        'lock_version',
        'client_token',
        'series_root_id',
        'occurrence_key',
    ];


 
    public function scopeVisibleTo(
        \Illuminate\Database\Eloquent\Builder $query,
        \App\Models\User $user
    ): \Illuminate\Database\Eloquent\Builder {
    
        // Managers / admins bypass the filter
        if (
            $user->hasPermission('collaboration.manage') ||
            $user->hasPermission('calendar.viewAll')
        ) {
            return $query;
        }
    
        return $query->where(function ($q) use ($user): void {
            $q->where('organizer_user_id', $user->id)
            ->orWhereHas('attendeeRecords', fn ($a) =>  // ← attendeeRecords not attendees
                $a->where('user_id', $user->id)
                    ->where('response', '!=', 'declined') // ← column is 'response' not 'response_status'
            );
        });
    }
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'attendees' => 'array',
            'reminders' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'lock_version' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function attendeeRecords(): HasMany { return $this->hasMany(CalendarEventAttendee::class); }
    public function recurrenceRule(): HasOne { return $this->hasOne(CalendarEventRecurrenceRule::class, 'root_event_id'); }
    public function reminderDeliveries(): HasMany { return $this->hasMany(CalendarEventReminderDelivery::class); }
    public function attachments(): HasMany { return $this->hasMany(CalendarEventAttachment::class); }
}
