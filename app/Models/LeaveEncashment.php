<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveEncashment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'payroll_marked_by_user_id',
        'encashment_number',
        'period_year',
        'status',
        'requested_days',
        'approved_days',
        'daily_rate',
        'gross_amount',
        'tax_amount',
        'net_amount',
        'calculation_snapshot',
        'request_note',
        'decision_note',
        'payroll_reference',
        'workflow_history',
        'approved_at',
        'payroll_marked_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'requested_days' => 'decimal:2',
            'approved_days' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'calculation_snapshot' => 'array',
            'workflow_history' => 'array',
            'approved_at' => 'datetime',
            'payroll_marked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function payrollMarkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payroll_marked_by_user_id');
    }
}
