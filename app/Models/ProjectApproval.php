<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectApproval extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'responsible_user_id',
        'verified_by_user_id',
        'approval_code',
        'approval_type',
        'authority_name',
        'application_number',
        'applied_on',
        'approved_on',
        'expires_on',
        'status',
        'required_for',
        'document_reference',
        'conditions',
        'workflow_history',
        'metadata',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_on' => 'date',
            'approved_on' => 'date',
            'expires_on' => 'date',
            'conditions' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'verified_at' => 'datetime',
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

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
