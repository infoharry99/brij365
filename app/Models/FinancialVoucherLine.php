<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialVoucherLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_voucher_id',
        'project_id',
        'line_number',
        'account_code',
        'account_name',
        'line_type',
        'amount',
        'party_type',
        'party_id',
        'cost_center',
        'tax_rate',
        'tax_amount',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FinancialVoucher::class, 'financial_voucher_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function party(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'party_type', 'party_id');
    }
}
