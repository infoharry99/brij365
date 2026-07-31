<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractorBill extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'vendor_id',
        'contractor_measurement_id',
        'prepared_by_user_id',
        'approved_by_user_id',
        'paid_by_user_id',
        'bill_number',
        'bill_date',
        'status',
        'gross_amount',
        'retention_percent',
        'retention_amount',
        'deduction_amount',
        'tax_amount',
        'payable_amount',
        'paid_amount',
        'balance_amount',
        'deductions',
        'payment_history',
        'workflow_history',
        'remarks',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'gross_amount' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'payable_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'deductions' => 'array',
            'payment_history' => 'array',
            'workflow_history' => 'array',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(ContractorMeasurement::class, 'contractor_measurement_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
