<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReraRegistration extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'verified_by_user_id',
        'registration_number',
        'authority_name',
        'state_code',
        'registered_on',
        'expires_on',
        'status',
        'document_reference',
        'conditions',
        'workflow_history',
        'metadata',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_on' => 'date',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
