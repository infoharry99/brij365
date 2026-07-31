<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringRecalculationRun extends Model
{
    protected $fillable = ['company_id', 'scoring_rule_id', 'triggered_by_user_id', 'status', 'total_records', 'processed_records', 'failed_records', 'started_at', 'completed_at', 'metadata'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function scoringRule(): BelongsTo { return $this->belongsTo(ScoringRule::class); }
    public function triggeredBy(): BelongsTo { return $this->belongsTo(User::class, 'triggered_by_user_id'); }
    public function failures(): HasMany { return $this->hasMany(ScoringRecalculationFailure::class); }
}
