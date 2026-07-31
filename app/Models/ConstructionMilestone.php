<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionMilestone extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'milestone_code',
        'name',
        'phase',
        'planned_start_on',
        'planned_end_on',
        'actual_start_on',
        'actual_end_on',
        'weight_percent',
        'progress_percent',
        'status',
        'dependencies',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_on' => 'date',
            'planned_end_on' => 'date',
            'actual_start_on' => 'date',
            'actual_end_on' => 'date',
            'weight_percent' => 'decimal:2',
            'progress_percent' => 'decimal:2',
            'dependencies' => 'array',
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

    public function boqItems(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }
}
