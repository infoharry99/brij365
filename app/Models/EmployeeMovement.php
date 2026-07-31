<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeMovement extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'transfer',
        'promotion',
        'department_change',
        'reporting_change',
        'salary_change',
        'status_change',
        'grade_change',
    ];

    public const STATUSES = ['pending', 'approved', 'cancelled'];

    protected $fillable = [
        'company_id',
        'employee_id',
        'movement_number',
        'movement_type',
        'effective_on',
        'status',
        'previous_values',
        'new_values',
        'reason',
        'remarks',
        'workflow_history',
        'metadata',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'approved_at' => 'datetime',
            'previous_values' => 'encrypted:array',
            'new_values' => 'encrypted:array',
            'workflow_history' => 'array',
            'metadata' => 'array',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
