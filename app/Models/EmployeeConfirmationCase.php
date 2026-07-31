<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeConfirmationCase extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'manager_employee_id',
        'created_by_user_id',
        'manager_reviewer_user_id',
        'hr_reviewer_user_id',
        'case_number',
        'status',
        'probation_starts_on',
        'probation_ends_on',
        'review_due_on',
        'manager_recommendation',
        'manager_comments',
        'review_scores',
        'hr_decision',
        'hr_comments',
        'confirmation_effective_on',
        'extended_until',
        'confirmation_letter_reference',
        'workflow_history',
        'manager_submitted_at',
        'hr_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'probation_starts_on' => 'date',
            'probation_ends_on' => 'date',
            'review_due_on' => 'date',
            'review_scores' => 'array',
            'confirmation_effective_on' => 'date',
            'extended_until' => 'date',
            'workflow_history' => 'array',
            'manager_submitted_at' => 'datetime',
            'hr_decided_at' => 'datetime',
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

    public function managerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function managerReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_reviewer_user_id');
    }

    public function hrReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_reviewer_user_id');
    }
}
