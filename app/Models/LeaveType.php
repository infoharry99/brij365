<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'annual_entitlement_days',
        'is_paid',
        'requires_document',
        'allows_half_day',
        'allow_negative_balance',
        'carry_forward_enabled',
        'max_carry_forward_days',
        'encashment_enabled',
        'approval_chain',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_entitlement_days' => 'decimal:2',
            'is_paid' => 'boolean',
            'requires_document' => 'boolean',
            'allows_half_day' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'carry_forward_enabled' => 'boolean',
            'max_carry_forward_days' => 'decimal:2',
            'encashment_enabled' => 'boolean',
            'approval_chain' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveEncashments(): HasMany
    {
        return $this->hasMany(LeaveEncashment::class);
    }
}
