<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionRule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'rule_code',
        'name',
        'rule_type',
        'basis',
        'rate_percent',
        'fixed_amount',
        'target_amount',
        'slab_rules',
        'eligibility_rules',
        'effective_from',
        'effective_to',
        'status',
        'workflow_history',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
            'target_amount' => 'decimal:2',
            'slab_rules' => 'array',
            'eligibility_rules' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'workflow_history' => 'array',
            'metadata' => 'array',
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

    public function runs(): HasMany
    {
        return $this->hasMany(CommissionRun::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommissionItem::class);
    }
}
