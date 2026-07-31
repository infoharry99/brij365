<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceReview extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'performance_cycle_id',
        'employee_id',
        'manager_employee_id',
        'self_reviewer_user_id',
        'manager_reviewer_user_id',
        'hr_reviewer_user_id',
        'review_number',
        'status',
        'period_start',
        'period_end',
        'self_submitted_at',
        'manager_submitted_at',
        'closed_at',
        'kpis',
        'kra_summary',
        'self_score',
        'manager_score',
        'final_score',
        'final_rating',
        'score_snapshot_id',
        'legacy_manual_scoring',
        'lock_version',
        'strengths',
        'improvement_areas',
        'manager_comments',
        'hr_comments',
        'pip_required',
        'pip_status',
        'pip_plan',
        'workflow_history',
        'scoring_inputs',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'self_submitted_at' => 'datetime',
            'manager_submitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'kpis' => 'array',
            'kra_summary' => 'array',
            'self_score' => 'decimal:2',
            'manager_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'legacy_manual_scoring' => 'boolean',
            'lock_version' => 'integer',
            'pip_required' => 'boolean',
            'pip_plan' => 'array',
            'workflow_history' => 'array',
            'scoring_inputs' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function managerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function selfReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'self_reviewer_user_id');
    }

    public function managerReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_reviewer_user_id');
    }

    public function hrReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_reviewer_user_id');
    }

    public function scoreSnapshot(): BelongsTo
    {
        return $this->belongsTo(ScoreSnapshot::class);
    }

    public function scoreOverrideRequests(): HasMany
    {
        return $this->hasMany(PerformanceScoreOverrideRequest::class);
    }
}
