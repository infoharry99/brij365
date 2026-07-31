<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceScoreOverrideRequest extends Model
{
    protected $fillable = [
        'company_id',
        'performance_review_id',
        'score_snapshot_id',
        'requested_by_user_id',
        'decided_by_user_id',
        'requested_score',
        'reason',
        'evidence',
        'status',
        'decision_reason',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_score' => 'decimal:4',
            'decided_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    public function scoreSnapshot(): BelongsTo
    {
        return $this->belongsTo(ScoreSnapshot::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
