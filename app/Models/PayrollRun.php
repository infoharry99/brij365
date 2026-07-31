<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'generated_by_user_id',
        'approved_by_user_id',
        'run_number',
        'period_year',
        'period_month',
        'period_start',
        'period_end',
        'working_days',
        'status',
        'gross_earnings',
        'total_deductions',
        'net_payable',
        'metadata',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PayrollRun $payrollRun): void {
            if ($payrollRun->getOriginal('status') === 'approved') {
                throw new \LogicException('Approved payroll runs are immutable.');
            }
        });

        static::deleting(function (PayrollRun $payrollRun): void {
            if ($payrollRun->status === 'approved') {
                throw new \LogicException('Approved payroll runs are immutable.');
            }
        });

        static::restoring(function (PayrollRun $payrollRun): void {
            if ($payrollRun->getOriginal('status') === 'approved') {
                throw new \LogicException('Approved payroll runs are immutable.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function bankTransferBatches(): HasMany
    {
        return $this->hasMany(PayrollBankTransferBatch::class);
    }
}
