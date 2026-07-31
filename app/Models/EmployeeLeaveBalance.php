<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'period_year',
        'opening_balance_days',
        'accrued_days',
        'used_days',
        'pending_days',
        'adjusted_days',
        'available_days',
        'ledger',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance_days' => 'decimal:2',
            'accrued_days' => 'decimal:2',
            'used_days' => 'decimal:2',
            'pending_days' => 'decimal:2',
            'adjusted_days' => 'decimal:2',
            'available_days' => 'decimal:2',
            'ledger' => 'array',
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
}
