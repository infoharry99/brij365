<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'customer_id',
        'partner_id',
        'marketing_campaign_id',
        'owner_user_id',
        'lead_code',
        'source',
        'stage',
        'status',
        'budget_min',
        'budget_max',
        'expected_value',
        'follow_up_at',
        'disposition_outcome',
        'disposition_reason',
        'competitor_name',
        'disposition_note',
        'dispositioned_by_user_id',
        'dispositioned_at',
    ];

    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'expected_value' => 'decimal:2',
            'follow_up_at' => 'datetime',
            'dispositioned_at' => 'datetime',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function marketingCampaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function dispositionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispositioned_by_user_id');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(LeadQualification::class);
    }

    public function latestQualification(): HasOne
    {
        return $this->hasOne(LeadQualification::class)->latestOfMany();
    }

    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }
}
