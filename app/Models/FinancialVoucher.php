<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialVoucher extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'approved_by_user_id',
        'voucher_number',
        'voucher_type',
        'status',
        'voucher_date',
        'reference_number',
        'narration',
        'currency',
        'total_debit',
        'total_credit',
        'tax_summary',
        'workflow_history',
        'metadata',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'tax_summary' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinancialVoucherLine::class);
    }
}
