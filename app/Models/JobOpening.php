<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOpening extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'project_id',
        'created_by_user_id',
        'reviewed_by_user_id',
        'opening_code',
        'title',
        'department',
        'designation',
        'positions',
        'employment_type',
        'work_location',
        'budget_min_ctc',
        'budget_max_ctc',
        'status',
        'target_hiring_date',
        'reviewed_at',
        'required_skills',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'positions' => 'integer',
            'budget_min_ctc' => 'decimal:2',
            'budget_max_ctc' => 'decimal:2',
            'target_hiring_date' => 'date',
            'reviewed_at' => 'datetime',
            'required_skills' => 'array',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
