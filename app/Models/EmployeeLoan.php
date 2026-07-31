<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLoan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'disbursed_by_user_id',
        'loan_number',
        'loan_type',
        'status',
        'principal_amount',
        'approved_amount',
        'installment_months',
        'monthly_installment',
        'requested_on',
        'repayment_starts_on',
        'purpose',
        'decision_note',
        'workflow_history',
        'approved_at',
        'disbursed_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'monthly_installment' => 'decimal:2',
            'requested_on' => 'date',
            'repayment_starts_on' => 'date',
            'workflow_history' => 'array',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function disbursedBy(): BelongsTo { return $this->belongsTo(User::class, 'disbursed_by_user_id'); }
}
