<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeTaxProfile extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'company_id',
        'employee_id',
        'supersedes_employee_tax_profile_id',
        'created_by_user_id',
        'submitted_by_user_id',
        'verified_by_user_id',
        'locked_by_user_id',
        'financial_year',
        'regime_code',
        'status',
        'version',
        'lock_version',
        'input_payload',
        'input_checksum',
        'workflow_history',
        'submitted_at',
        'verified_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'lock_version' => 'integer',
            'input_payload' => 'encrypted:array',
            'workflow_history' => 'array',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $profile): void {
            if ($profile->getOriginal('status') === self::STATUS_LOCKED) {
                throw new \LogicException('Locked employee tax profiles are immutable.');
            }
        });

        static::deleting(function (self $profile): void {
            if ($profile->status === self::STATUS_LOCKED) {
                throw new \LogicException('Locked employee tax profiles are immutable.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_employee_tax_profile_id');
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_employee_tax_profile_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(EmployeeTaxDeclaration::class);
    }
}
