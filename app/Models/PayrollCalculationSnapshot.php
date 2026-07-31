<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollCalculationSnapshot extends Model
{
    protected $fillable = [
        'payroll_run_item_id',
        'payroll_attendance_snapshot_id',
        'salary_assignment_id',
        'company_id',
        'employee_id',
        'created_by_user_id',
        'currency',
        'calculation_version',
        'gross_minor',
        'deduction_minor',
        'employer_contribution_minor',
        'net_minor',
        'input_hash',
        'result_hash',
        'rule_context',
        'input_snapshot',
        'calculation_trace',
    ];

    protected function casts(): array
    {
        return [
            'calculation_version' => 'integer',
            'gross_minor' => 'integer',
            'deduction_minor' => 'integer',
            'employer_contribution_minor' => 'integer',
            'net_minor' => 'integer',
            'rule_context' => 'array',
            'input_snapshot' => 'array',
            'calculation_trace' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Payroll calculation snapshots are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Payroll calculation snapshots are immutable.'));
    }

    public function payrollRunItem(): BelongsTo
    {
        return $this->belongsTo(PayrollRunItem::class);
    }

    public function attendanceSnapshot(): BelongsTo
    {
        return $this->belongsTo(PayrollAttendanceSnapshot::class, 'payroll_attendance_snapshot_id');
    }

    public function salaryAssignment(): BelongsTo
    {
        return $this->belongsTo(SalaryAssignment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollCalculationLine::class)->orderBy('sort_order');
    }
}
