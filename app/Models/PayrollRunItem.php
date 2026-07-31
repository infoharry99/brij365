<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollRunItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'company_id',
        'employee_id',
        'salary_structure_id',
        'monthly_ctc',
        'payable_days',
        'gross_earnings',
        'total_deductions',
        'net_payable',
        'component_breakup',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'monthly_ctc' => 'decimal:2',
            'payable_days' => 'decimal:2',
            'gross_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'component_breakup' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PayrollRunItem $payrollRunItem): void {
            $payrollRunItem->assertPayrollRunIsMutable();
        });

        static::deleting(function (PayrollRunItem $payrollRunItem): void {
            $payrollRunItem->assertPayrollRunIsMutable();
        });
    }

    private function assertPayrollRunIsMutable(): void
    {
        $payrollRunIds = collect([
            $this->getOriginal('payroll_run_id'),
            $this->payroll_run_id,
        ])
            ->filter(fn (mixed $id): bool => (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($payrollRunIds->isNotEmpty() && PayrollRun::query()
            ->withTrashed()
            ->whereKey($payrollRunIds->all())
            ->where('status', 'approved')
            ->exists()) {
            throw new \LogicException('Approved payroll run items are immutable.');
        }
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function bankTransferItems(): HasMany
    {
        return $this->hasMany(PayrollBankTransferItem::class);
    }

    public function commissionItems(): HasMany
    {
        return $this->hasMany(CommissionItem::class);
    }

    public function calculationSnapshot(): HasOne
    {
        return $this->hasOne(PayrollCalculationSnapshot::class);
    }
}
