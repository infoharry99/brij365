<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxDeclaration extends Model
{
    protected $fillable = [
        'employee_tax_profile_id',
        'managed_document_id',
        'category_code',
        'declaration_type',
        'status',
        'amount_payload',
        'decision_note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_payload' => 'encrypted:array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $declaration): void {
            if ($declaration->profile?->status === EmployeeTaxProfile::STATUS_LOCKED) {
                throw new \LogicException('Declarations belonging to a locked employee tax profile are immutable.');
            }
        });

        static::deleting(function (self $declaration): void {
            if ($declaration->profile?->status === EmployeeTaxProfile::STATUS_LOCKED) {
                throw new \LogicException('Declarations belonging to a locked employee tax profile are immutable.');
            }
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EmployeeTaxProfile::class, 'employee_tax_profile_id');
    }

    public function proofDocument(): BelongsTo
    {
        return $this->belongsTo(ManagedDocument::class, 'managed_document_id');
    }
}
