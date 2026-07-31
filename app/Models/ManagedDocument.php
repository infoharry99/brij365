<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManagedDocument extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $document): void {
            if ($document->taxDeclarations()->exists()) {
                throw new \LogicException('A document pinned as employee tax proof cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'company_id',
        'document_category_id',
        'uploaded_by_user_id',
        'approved_by_user_id',
        'document_number',
        'title',
        'owner_type',
        'owner_id',
        'status',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'issue_date',
        'expires_on',
        'version',
        'is_current',
        'metadata',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expires_on' => 'date',
            'is_current' => 'boolean',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function employeeOwner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function taxDeclarations(): HasMany
    {
        return $this->hasMany(EmployeeTaxDeclaration::class, 'managed_document_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    public function isExpiringWithin(int $days): bool
    {
        return $this->expires_on !== null
            && $this->expires_on->isFuture()
            && $this->expires_on->lte(now()->addDays($days));
    }
}
