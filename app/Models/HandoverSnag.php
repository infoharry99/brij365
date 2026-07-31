<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HandoverSnag extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'possession_handover_id',
        'reported_by_user_id',
        'resolved_by_user_id',
        'snag_number',
        'area',
        'category',
        'severity',
        'description',
        'status',
        'target_resolution_on',
        'resolved_at',
        'resolution_notes',
        'attachments',
        'workflow_history',
    ];

    protected function casts(): array
    {
        return [
            'target_resolution_on' => 'date',
            'resolved_at' => 'datetime',
            'attachments' => 'array',
            'workflow_history' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(PossessionHandover::class, 'possession_handover_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
