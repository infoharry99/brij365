<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeExitInterview extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'employee_separation_settlement_id',
        'scheduled_by_user_id',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'interview_number',
        'status',
        'interview_due_on',
        'submitted_at',
        'reviewed_at',
        'separation_reason',
        'rehire_recommendation',
        'overall_experience_rating',
        'manager_relationship_rating',
        'workload_rating',
        'compensation_rating',
        'public_feedback',
        'improvement_suggestions',
        'confidential_responses',
        'risk_flags',
        'questionnaire_template',
        'hr_review_notes',
        'action_items',
        'workflow_history',
        'scoring_inputs',
    ];

    protected function casts(): array
    {
        return [
            'interview_due_on' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'overall_experience_rating' => 'integer',
            'manager_relationship_rating' => 'integer',
            'workload_rating' => 'integer',
            'compensation_rating' => 'integer',
            'confidential_responses' => 'encrypted:array',
            'risk_flags' => 'array',
            'questionnaire_template' => 'array',
            'action_items' => 'array',
            'workflow_history' => 'array',
            'scoring_inputs' => 'array',
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

    public function separationSettlement(): BelongsTo
    {
        return $this->belongsTo(EmployeeSeparationSettlement::class, 'employee_separation_settlement_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
