<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ScoreSnapshot extends Model
{
    protected $fillable = [
        'company_id', 'scoring_rule_id', 'overridden_from_snapshot_id', 'overridden_by_user_id',
        'subject_type', 'subject_id', 'total_score', 'component_scores', 'applied_weights', 'score_band',
        'input_snapshot', 'input_hash', 'rule_version', 'is_current', 'is_override', 'override_reason',
        'overridden_at', 'calculated_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:4', 'component_scores' => 'array', 'applied_weights' => 'array',
            'input_snapshot' => 'array', 'is_current' => 'boolean', 'is_override' => 'boolean',
            'overridden_at' => 'datetime', 'calculated_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Calculated score snapshots are immutable and cannot be updated in place.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Calculated score snapshots are immutable and cannot be deleted.');
        });
    }

    /**
     * Retire the current marker without rewriting any calculation evidence.
     *
     * Snapshot replacement is append-only. This is the sole model-level
     * lifecycle transition used by the governed snapshot writers.
     */
    public function markHistorical(): void
    {
        if (! $this->exists || ! $this->is_current) {
            return;
        }

        static::withoutEvents(function (): void {
            $this->forceFill([
                'is_current' => false,
                'updated_at' => now(),
            ])->save();
        });

        $this->syncOriginal();
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function scoringRule(): BelongsTo { return $this->belongsTo(ScoringRule::class); }
    public function overriddenFrom(): BelongsTo { return $this->belongsTo(self::class, 'overridden_from_snapshot_id'); }
    public function overriddenBy(): BelongsTo { return $this->belongsTo(User::class, 'overridden_by_user_id'); }
}
