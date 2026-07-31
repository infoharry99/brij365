<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSeparationSettlement extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'initiated_by_user_id',
        'hr_approved_by_user_id',
        'finance_approved_by_user_id',
        'completed_by_user_id',
        'settlement_number',
        'separation_type',
        'status',
        'resignation_date',
        'last_working_date',
        'settlement_date',
        'reason',
        'calculation_breakdown',
        'clearance_blockers',
        'last_salary_amount',
        'leave_encashment_amount',
        'bonus_amount',
        'gratuity_amount',
        'claim_payable_amount',
        'notice_recovery_amount',
        'loan_recovery_amount',
        'asset_recovery_amount',
        'tax_recovery_amount',
        'gross_payable',
        'total_recoveries',
        'net_payable',
        'payment_reference',
        'workflow_history',
        'hr_approved_at',
        'finance_approved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'resignation_date' => 'date',
            'last_working_date' => 'date',
            'settlement_date' => 'date',
            'calculation_breakdown' => 'array',
            'clearance_blockers' => 'array',
            'last_salary_amount' => 'decimal:2',
            'leave_encashment_amount' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'gratuity_amount' => 'decimal:2',
            'claim_payable_amount' => 'decimal:2',
            'notice_recovery_amount' => 'decimal:2',
            'loan_recovery_amount' => 'decimal:2',
            'asset_recovery_amount' => 'decimal:2',
            'tax_recovery_amount' => 'decimal:2',
            'gross_payable' => 'decimal:2',
            'total_recoveries' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'workflow_history' => 'array',
            'hr_approved_at' => 'datetime',
            'finance_approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function initiatedBy(): BelongsTo { return $this->belongsTo(User::class, 'initiated_by_user_id'); }
    public function hrApprovedBy(): BelongsTo { return $this->belongsTo(User::class, 'hr_approved_by_user_id'); }
    public function financeApprovedBy(): BelongsTo { return $this->belongsTo(User::class, 'finance_approved_by_user_id'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by_user_id'); }
}
