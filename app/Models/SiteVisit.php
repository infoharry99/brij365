<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteVisit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'lead_id',
        'customer_id',
        'scheduled_by_user_id',
        'assigned_to_user_id',
        'visit_number',
        'status',
        'scheduled_at',
        'duration_minutes',
        'visit_mode',
        'meeting_location',
        'meeting_url',
        'agenda',
        'outcome_notes',
        'outcome',
        'completed_at',
        'cancelled_at',
        'next_follow_up_at',
        'attendees',
        'workflow_history',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'attendees' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
