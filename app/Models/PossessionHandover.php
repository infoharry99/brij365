<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PossessionHandover extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'booking_id',
        'customer_id',
        'project_unit_id',
        'initiated_by_user_id',
        'completed_by_user_id',
        'handover_number',
        'target_handover_on',
        'actual_handover_on',
        'status',
        'financial_outstanding',
        'checklist',
        'blockers',
        'possession_letter_reference',
        'workflow_history',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_handover_on' => 'date',
            'actual_handover_on' => 'date',
            'financial_outstanding' => 'decimal:2',
            'checklist' => 'array',
            'blockers' => 'array',
            'workflow_history' => 'array',
            'completed_at' => 'datetime',
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function snags(): HasMany
    {
        return $this->hasMany(HandoverSnag::class);
    }
}
