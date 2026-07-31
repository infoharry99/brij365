<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GstReturnPeriod extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'prepared_by_user_id',
        'approved_by_user_id',
        'locked_by_user_id',
        'return_number',
        'period_year',
        'period_month',
        'period_start',
        'period_end',
        'status',
        'entry_count',
        'output_taxable_total',
        'output_tax_total',
        'input_taxable_total',
        'input_tax_credit_total',
        'net_tax_payable',
        'summary',
        'workflow_history',
        'approved_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'output_taxable_total' => 'decimal:2',
            'output_tax_total' => 'decimal:2',
            'input_taxable_total' => 'decimal:2',
            'input_tax_credit_total' => 'decimal:2',
            'net_tax_payable' => 'decimal:2',
            'summary' => 'array',
            'workflow_history' => 'array',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }
}
