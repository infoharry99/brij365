<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceCycle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'activated_by_user_id',
        'cycle_code',
        'name',
        'frequency',
        'status',
        'starts_on',
        'ends_on',
        'review_due_on',
        'department',
        'rating_scale_min',
        'rating_scale_max',
        'passing_score',
        'rules',
        'workflow_history',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'review_due_on' => 'date',
            'passing_score' => 'decimal:2',
            'rules' => 'array',
            'workflow_history' => 'array',
            'activated_at' => 'datetime',
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

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }
}
