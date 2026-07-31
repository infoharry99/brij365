<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScoringAggregateSubject extends Model
{
    protected $fillable = ['company_id', 'subject_type', 'subject_key', 'label', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
