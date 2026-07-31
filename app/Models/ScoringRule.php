<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringRule extends Model
{
    private const GOVERNED_STATUSES = [
        'pending_approval',
        'approved',
        'scheduled',
        'active',
        'superseded',
        'retired',
    ];

    private const LIFECYCLE_FIELDS = [
        'status',
        'approved_by_user_id',
        'activated_by_user_id',
        'submitted_at',
        'approved_at',
        'activated_at',
        'retired_at',
        'effective_at',
        'metadata',
    ];

    private const LIFECYCLE_METADATA_KEYS = [
        'rejection_reason',
        'rejected_at',
        'retirement_reason',
    ];

    private bool $persistingGovernedLifecycle = false;

    protected $fillable = [
        'company_id', 'previous_rule_id', 'created_by_user_id', 'approved_by_user_id', 'activated_by_user_id',
        'rule_key', 'name', 'version', 'status', 'configuration', 'configuration_checksum', 'change_reason',
        'effective_at', 'submitted_at', 'approved_at', 'activated_at', 'retired_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'metadata' => 'array',
            'effective_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $rule): void {
            if ($rule->persistingGovernedLifecycle) {
                return;
            }

            $persistedStatus = (string) self::query()->whereKey($rule->getKey())->value('status');
            if (! in_array($persistedStatus, self::GOVERNED_STATUSES, true)) {
                return;
            }

            $changed = array_values(array_diff(array_keys($rule->getDirty()), ['updated_at']));
            if ($changed !== []) {
                throw new LogicException('Submitted scoring-rule versions are immutable outside the governed lifecycle service.');
            }
        });

        static::deleting(static function (): never {
            throw new LogicException('Scoring-rule versions cannot be deleted because their governed evidence must be retained.');
        });
    }

    /**
     * Persist only lifecycle state controlled by ScoringRuleLifecycleService.
     *
     * @param array<string, mixed> $attributes
     */
    public function persistGovernedLifecycle(array $attributes): bool
    {
        $unsupported = array_values(array_diff(array_keys($attributes), self::LIFECYCLE_FIELDS));
        if ($unsupported !== []) {
            throw new LogicException('Unsupported governed scoring-rule lifecycle fields: '.implode(', ', $unsupported).'.');
        }

        $this->assertLifecycleMetadataIsSafe($attributes);
        $this->assertLifecycleEffectiveTimeIsSafe($attributes);

        $this->persistingGovernedLifecycle = true;

        try {
            return $this->forceFill($attributes)->save();
        } finally {
            $this->persistingGovernedLifecycle = false;
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertLifecycleMetadataIsSafe(array $attributes): void
    {
        if (! array_key_exists('metadata', $attributes)) {
            return;
        }

        $current = $this->metadata ?? [];
        $next = is_array($attributes['metadata']) ? $attributes['metadata'] : [];
        $keys = array_unique([...array_keys($current), ...array_keys($next)]);
        $changedKeys = array_filter($keys, static fn (string|int $key): bool => ($current[$key] ?? null) !== ($next[$key] ?? null));
        $unsupported = array_values(array_diff($changedKeys, self::LIFECYCLE_METADATA_KEYS));

        if ($unsupported !== []) {
            throw new LogicException('Governed scoring-rule evidence metadata is immutable after submission.');
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertLifecycleEffectiveTimeIsSafe(array $attributes): void
    {
        if (! array_key_exists('effective_at', $attributes)) {
            return;
        }

        $current = $this->effective_at;
        $next = $attributes['effective_at'] === null ? null : CarbonImmutable::parse($attributes['effective_at']);
        if (($current === null && $next === null) || ($current !== null && $next !== null && $current->equalTo($next))) {
            return;
        }

        $targetStatus = (string) ($attributes['status'] ?? $this->status);
        if ($current === null && $targetStatus === 'active' && $next !== null) {
            return;
        }

        throw new LogicException('The governed scoring-rule effective time is immutable after submission.');
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function previousRule(): BelongsTo { return $this->belongsTo(self::class, 'previous_rule_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function activatedBy(): BelongsTo { return $this->belongsTo(User::class, 'activated_by_user_id'); }
    public function snapshots(): HasMany { return $this->hasMany(ScoreSnapshot::class); }
    public function recalculationRuns(): HasMany { return $this->hasMany(ScoringRecalculationRun::class); }
}
