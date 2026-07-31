<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'approved_by_user_id',
        'scope_key',
        'setting_group',
        'setting_key',
        'label',
        'description',
        'value_type',
        'value',
        'status',
        'version',
        'effective_from',
        'effective_to',
        'approved_at',
        'workflow_history',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
            'workflow_history' => 'array',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function statutoryVerification(): HasOne
    {
        return $this->hasOne(StatutoryRuleVerification::class);
    }
}
