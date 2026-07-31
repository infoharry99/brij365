<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringRecalculationFailure extends Model
{
    protected $fillable = ['scoring_recalculation_run_id', 'subject_type', 'subject_id', 'error_code', 'error_message', 'context'];
    protected function casts(): array { return ['context' => 'array']; }
    public function run(): BelongsTo { return $this->belongsTo(ScoringRecalculationRun::class, 'scoring_recalculation_run_id'); }
}
