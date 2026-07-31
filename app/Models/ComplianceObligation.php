<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceObligation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'assigned_to_user_id',
        'completed_by_user_id',
        'obligation_number',
        'title',
        'compliance_type',
        'due_on',
        'frequency',
        'priority',
        'status',
        'evidence_document_reference',
        'notes',
        'workflow_history',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
