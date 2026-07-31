<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCampaign extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'approved_by_user_id',
        'campaign_code',
        'name',
        'channel',
        'source',
        'status',
        'start_on',
        'end_on',
        'budget_amount',
        'target_leads',
        'target_bookings',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'audience_segment',
        'workflow_history',
        'metadata',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'start_on' => 'date',
            'end_on' => 'date',
            'budget_amount' => 'decimal:2',
            'target_leads' => 'integer',
            'target_bookings' => 'integer',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }
}
