<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocietyFormation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'updated_by_user_id',
        'formation_number',
        'society_name',
        'association_type',
        'total_units',
        'occupied_units',
        'registration_number',
        'application_filed_on',
        'registered_on',
        'target_handover_on',
        'status',
        'progress_percent',
        'current_stage',
        'next_step',
        'committee_members',
        'workflow_history',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'application_filed_on' => 'date',
            'registered_on' => 'date',
            'target_handover_on' => 'date',
            'committee_members' => 'array',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function handoverItems(): HasMany
    {
        return $this->hasMany(CommonAreaHandoverItem::class);
    }
}
