<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAttendanceSnapshot extends Model
{
    protected $fillable = ['attendance_period_lock_id', 'company_id', 'employee_id', 'period_start', 'period_end', 'scheduled_days', 'present_days', 'paid_leave_days', 'unpaid_days', 'worked_minutes', 'payable_days', 'source_hash', 'calculation_trace'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'payable_days' => 'decimal:2', 'calculation_trace' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Payroll attendance snapshots are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Payroll attendance snapshots are immutable.'));
    }

    public function periodLock(): BelongsTo { return $this->belongsTo(AttendancePeriodLock::class, 'attendance_period_lock_id'); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
