<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadQualification extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'lead_id',
        'qualified_by_user_id',
        'qualification_number',
        'status',
        'score',
        'budget_score',
        'authority_score',
        'need_score',
        'timeline_score',
        'preferred_configuration',
        'verified_budget_min',
        'verified_budget_max',
        'expected_booking_date',
        'decision_notes',
        'requirements',
        'workflow_history',
        'metadata',
        'qualified_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'budget_score' => 'integer',
            'authority_score' => 'integer',
            'need_score' => 'integer',
            'timeline_score' => 'integer',
            'verified_budget_min' => 'decimal:2',
            'verified_budget_max' => 'decimal:2',
            'expected_booking_date' => 'date',
            'requirements' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'qualified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function qualifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qualified_by_user_id');
    }
}
